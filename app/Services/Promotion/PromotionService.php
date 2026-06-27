<?php

namespace App\Services\Promotion;

use App\CentralLogics\Helpers;
use App\Model\Product;
use App\Model\ProductByBranch;
use App\Model\Promotion;
use App\Model\PromotionGroupProduct;
use App\Model\PromotionItemGroup;
use App\Model\PromotionRedemption;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function __construct(
        private PromotionEligibility $eligibility
    ) {}

    /**
     * @return array{promotion_discount_amount: float, promotion_id: int|null, errors: array}
     */
    public function applyToCart(
        array $cart,
        ?int $promotionId,
        int $userId,
        int $isGuest,
        array $deliveryChargeInfo,
        ?string $couponCode = null,
        bool $rejectOnExclusiveConflict = false
    ): array {
        if (!$promotionId) {
            return [
                'promotion_discount_amount' => 0,
                'promotion_id' => null,
            ];
        }

        if ($isGuest === 1) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('Please login to use this promotion')],
            ]);
        }

        $promotion = Promotion::with(['itemGroups.groupProducts'])->active()->find($promotionId);
        if (!$promotion) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('Promotion not found or inactive')],
            ]);
        }

        $orderType = $deliveryChargeInfo['order_type'] ?? 'any';
        $eligibility = $this->eligibility->check($promotion, $userId, $orderType);
        if (!$eligibility['eligible']) {
            throw ValidationException::withMessages([
                'promotion_id' => [$eligibility['reason'] ?? translate('You are not eligible for this promotion')],
            ]);
        }

        $this->validateCartStructure($cart, $promotion, $deliveryChargeInfo);

        $discountAmount = $this->calculatePromotionDiscount($cart, $promotion, $deliveryChargeInfo);

        if ($discountAmount > 0 && $promotion->is_exclusive && !empty($couponCode)) {
            if ($rejectOnExclusiveConflict) {
                throw ValidationException::withMessages([
                    'coupon_code' => [translate('This promotion cannot be combined with coupons')],
                ]);
            }
            $couponCode = null;
        }

        return [
            'promotion_discount_amount' => $discountAmount,
            'promotion_id' => $promotion->id,
            'coupon_code_blocked' => $promotion->is_exclusive && $discountAmount > 0 ? true : false,
        ];
    }

    private function validateCartStructure(array $cart, Promotion $promotion, array $deliveryChargeInfo): void
    {
        $promoLines = array_values(array_filter($cart, function ($item) use ($promotion) {
            return (int) ($item['promotion_id'] ?? 0) === (int) $promotion->id;
        }));

        if (count($promoLines) < 2) {
            throw ValidationException::withMessages([
                'cart' => [translate('Promotion requires both paid and reward items in cart')],
            ]);
        }

        $paidLines = array_filter($promoLines, fn ($item) => ($item['promotion_role'] ?? '') === 'paid');
        $rewardLines = array_filter($promoLines, fn ($item) => ($item['promotion_role'] ?? '') === 'reward');

        if (count($paidLines) !== 1 || count($rewardLines) !== 1) {
            throw ValidationException::withMessages([
                'cart' => [translate('Promotion requires exactly one paid item and one reward item')],
            ]);
        }

        $paidItem = array_values($paidLines)[0];
        $rewardItem = array_values($rewardLines)[0];

        $groupProductMap = $this->buildGroupProductMap($promotion);

        if (!$this->productInGroup((int) $paidItem['product_id'], 1, $groupProductMap)) {
            throw ValidationException::withMessages([
                'cart' => [translate('Paid item is not eligible for this promotion')],
            ]);
        }

        if (!$this->productInGroup((int) $rewardItem['product_id'], 2, $groupProductMap)) {
            // Allow same-group BOGO when only group 1 exists
            $hasGroup2 = isset($groupProductMap[2]) && count($groupProductMap[2]) > 0;
            if ($hasGroup2 || !$this->productInGroup((int) $rewardItem['product_id'], 1, $groupProductMap)) {
                throw ValidationException::withMessages([
                    'cart' => [translate('Reward item is not eligible for this promotion')],
                ]);
            }
        }
    }

    private function buildGroupProductMap(Promotion $promotion): array
    {
        $map = [1 => [], 2 => []];
        foreach ($promotion->itemGroups as $group) {
            $productIds = $group->groupProducts->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $map[(int) $group->group_number] = $productIds;
        }
        return $map;
    }

    private function productInGroup(int $productId, int $groupNumber, array $groupProductMap): bool
    {
        return in_array($productId, $groupProductMap[$groupNumber] ?? [], true);
    }

    private function calculatePromotionDiscount(array $cart, Promotion $promotion, array $deliveryChargeInfo): float
    {
        $promoLines = array_values(array_filter($cart, function ($item) use ($promotion) {
            return (int) ($item['promotion_id'] ?? 0) === (int) $promotion->id;
        }));

        $lineAmounts = [];
        foreach ($promoLines as $item) {
            $lineAmounts[] = $this->calculateLineNetAmount(
                $item,
                $deliveryChargeInfo,
                $promotion,
                ($item['promotion_role'] ?? '') === 'reward'
            );
        }

        if (count($lineAmounts) < 2) {
            return 0;
        }

        $cheaper = min($lineAmounts);
        $expensive = max($lineAmounts);

        $cheaperDiscount = $cheaper * ((float) $promotion->discount_cheapest_percent / 100);
        $expensiveDiscount = $expensive * ((float) $promotion->discount_expensive_percent / 100);

        return round($cheaperDiscount + $expensiveDiscount, 2);
    }

    private function calculateLineNetAmount(
        array $cartItem,
        array $deliveryChargeInfo,
        Promotion $promotion,
        bool $isRewardLine
    ): float {
        $product = Product::find($cartItem['product_id']);
        if (!$product) {
            return 0;
        }

        $branchId = $deliveryChargeInfo['branch_id'] ?? null;
        $branchProduct = $branchId
            ? ProductByBranch::where(['product_id' => $cartItem['product_id'], 'branch_id' => $branchId])->first()
            : null;

        $discountData = [];
        $variations = $cartItem['variations'] ?? [];
        $variationData = ['variations' => [], 'price' => 0];

        if ($isRewardLine && !$promotion->charge_modifier_addons) {
            $variations = [];
            $cartItem['add_on_ids'] = [];
            $cartItem['add_on_qtys'] = [];
        }

        if ($branchProduct) {
            $branchProductVariations = $branchProduct->variations ?? [];
            if (!is_array($branchProductVariations)) {
                $branchProductVariations = json_decode($branchProductVariations ?? '[]', true) ?: [];
            }

            if (count($branchProductVariations) && !($isRewardLine && !$promotion->charge_modifier_addons)) {
                $variationData = Helpers::get_varient($branchProductVariations, $variations);
                $price = $branchProduct['price'] + $variationData['price'];
            } else {
                $price = $branchProduct['price'];
            }

            $discountData = [
                'discount_type' => $branchProduct['discount_type'],
                'discount' => $branchProduct['discount'],
            ];
        } else {
            $productVariations = json_decode($product->variations, true) ?: [];
            if (count($productVariations) && !($isRewardLine && !$promotion->charge_modifier_addons)) {
                $variationData = Helpers::get_varient($productVariations, $variations);
                $price = $product['price'] + $variationData['price'];
            } else {
                $price = $product['price'];
            }

            $discountData = [
                'discount_type' => $product['discount_type'],
                'discount' => $product['discount'],
            ];
        }

        $discountOnProduct = Helpers::discount_calculate($discountData, $price);
        $netProduct = ($price - $discountOnProduct) * (int) ($cartItem['quantity'] ?? 1);

        $addonPrice = 0;
        if (!($isRewardLine && !$promotion->charge_modifier_addons)) {
            $pricedVariationLabels = [];
            if (!empty($variationData['variations'] ?? null)) {
                $pricedVariationLabels = Helpers::extract_priced_variation_option_labels($variationData['variations']);
            }
            $normalizedAddons = Helpers::normalize_order_addons(
                product: $product,
                selectedVariations: $cartItem['variations'] ?? [],
                selectedAddonIds: $cartItem['add_on_ids'] ?? [],
                selectedAddonQtys: $cartItem['add_on_qtys'] ?? [],
                excludeAddonLabels: $pricedVariationLabels,
            );
            $addonPrice = (float) $normalizedAddons['total_add_on_price'];
        }

        return $netProduct + $addonPrice;
    }

    public function formatForApi(Promotion $promotion): array
    {
        $promotion->loadMissing(['itemGroups.groupProducts.product']);

        $groups = $promotion->itemGroups->map(function (PromotionItemGroup $group) {
            $products = $group->groupProducts->map(function (PromotionGroupProduct $groupProduct) {
                $product = $groupProduct->product;
                if (!$product) {
                    return null;
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $product->image ?? null,
                    'price' => $product->price,
                ];
            })->filter()->values();

            return [
                'id' => $group->id,
                'group_number' => $group->group_number,
                'label' => $group->label,
                'products' => $products,
            ];
        })->values();

        return [
            'id' => $promotion->id,
            'title' => $promotion->title,
            'headline' => $promotion->headline,
            'description' => $promotion->description,
            'image' => $promotion->image_full_path,
            'type' => $promotion->type,
            'discount_cheapest_percent' => $promotion->discount_cheapest_percent,
            'discount_expensive_percent' => $promotion->discount_expensive_percent,
            'charge_modifier_addons' => (bool) $promotion->charge_modifier_addons,
            'customer_type' => $promotion->customer_type,
            'order_type' => $promotion->order_type,
            'once_per_customer' => (bool) $promotion->once_per_customer,
            'is_exclusive' => (bool) $promotion->is_exclusive,
            'highlight_group' => $promotion->highlight_group,
            'start_date' => $promotion->start_date?->toDateString(),
            'end_date' => $promotion->end_date?->toDateString(),
            'item_groups' => $groups,
        ];
    }

    public function recordRedemption(int $promotionId, int $userId, int $orderId): void
    {
        PromotionRedemption::create([
            'promotion_id' => $promotionId,
            'user_id' => $userId,
            'order_id' => $orderId,
            'redeemed_at' => now(),
        ]);
    }
}
