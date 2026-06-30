<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Model\Banner;
use App\Model\BannerGroupItem;
use App\Model\Order;
use App\Model\PromotionUsage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PromoOrderService
{
    public function findActiveBanner(int $promotionId): ?Banner
    {
        $query = Banner::active()->currentlyValid();

        if (Schema::hasTable('banner_group_items')) {
            $query->with('groupItems');
        }

        return $query->find($promotionId);
    }

    public function validatePromotion(
        Banner $banner,
        array $cart,
        int|string|null $userId,
        int $isGuest,
        string $orderType,
        ?string $paymentMethod = null,
    ): void {
        if (!$banner->isOrderTypeAllowed($orderType)) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('Promotion is not available for this order type')],
            ]);
        }

        if ($paymentMethod !== null && !$banner->isPaymentMethodAllowed($paymentMethod)) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('Promotion is not available for this payment method')],
            ]);
        }

        $usageQuery = PromotionUsage::query()->where('banner_id', $banner->id);
        if ($isGuest === 0 && $userId) {
            $usageQuery->where('user_id', $userId);
        } elseif ($userId) {
            $usageQuery->where('guest_id', $userId);
        }

        $customerUsageCount = (clone $usageQuery)->count();

        if ($banner->once_per_customer && $customerUsageCount > 0) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('Promotion can only be used once per customer')],
            ]);
        }

        if ($banner->usage_per_customer && $customerUsageCount >= $banner->usage_per_customer) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('Promotion usage limit reached for this customer')],
            ]);
        }

        $this->validateCustomerEligibility($banner, $userId, $isGuest);

        $promoLines = collect($cart)->filter(
            fn ($line) => (int) ($line['promotion_id'] ?? 0) === (int) $banner->id
        );

        $paidLines = $promoLines->filter(fn ($line) => ($line['promotion_role'] ?? null) === 'paid');
        $rewardLines = $promoLines->filter(fn ($line) => ($line['promotion_role'] ?? null) === 'reward');

        if ($this->usesSingleGroupDiscount($banner)) {
            if ($paidLines->count() !== 1 || $rewardLines->count() !== 0) {
                throw ValidationException::withMessages([
                    'promotion_id' => [translate('Invalid promotion cart items')],
                ]);
            }

            if (!$this->lineMatchesGroupItem($banner, 1, $paidLines->first())) {
                throw ValidationException::withMessages([
                    'promotion_id' => [translate('Selected item is not eligible for this promotion')],
                ]);
            }

            return;
        }

        if ($paidLines->count() !== 1 || $rewardLines->count() !== 1) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('Invalid promotion cart items')],
            ]);
        }

        $paidLine = $paidLines->first();
        $rewardLine = $rewardLines->first();

        $maxRewardQty = max(1, (int) ($banner->max_reward_qty ?? 1));
        if ((int) ($rewardLine['quantity'] ?? 1) > $maxRewardQty) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('Maximum reward quantity exceeded')],
            ]);
        }

        if (!$this->lineMatchesGroupItem($banner, 1, $paidLine)) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('Selected paid item is not eligible for this promotion')],
            ]);
        }

        if (!$this->lineMatchesGroupItem($banner, 2, $rewardLine)) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('Selected reward item is not eligible for this promotion')],
            ]);
        }
    }

    public function validateCustomerEligibility(Banner $banner, int|string|null $userId, int $isGuest): void
    {
        $eligibility = $banner->customer_eligibility ?? 'any';
        if ($eligibility === 'any' || !$userId) {
            return;
        }

        $hasPriorOrders = Order::query()
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->exists();

        if ($eligibility === 'new' && $hasPriorOrders) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('This promotion is only available for new customers')],
            ]);
        }

        if ($eligibility === 'returned' && !$hasPriorOrders) {
            throw ValidationException::withMessages([
                'promotion_id' => [translate('This promotion is only available for returning customers')],
            ]);
        }
    }

    public function lineMatchesGroupItem(Banner $banner, int $groupNumber, array $cartLine): bool
    {
        $productId = (int) ($cartLine['product_id'] ?? 0);
        $variations = $cartLine['variations'] ?? [];

        return $banner->groupItems
            ->where('group_number', $groupNumber)
            ->contains(function (BannerGroupItem $item) use ($productId, $variations) {
                return (int) $item->product_id === $productId
                    && $this->variationsMatch($item->variations ?? [], $variations);
            });
    }

    public function variationsMatch(array $expected, array $actual): bool
    {
        $expectedNormalized = $this->normalizeVariationLabels($expected);
        $actualNormalized = $this->normalizeVariationLabels($actual);

        if (empty($expectedNormalized)) {
            return true;
        }

        foreach ($expectedNormalized as $name => $expectedLabels) {
            if (!isset($actualNormalized[$name])) {
                return false;
            }

            foreach ($expectedLabels as $label) {
                if (!in_array($label, $actualNormalized[$name], true)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function normalizeVariationLabels(array $variations): array
    {
        $normalized = [];

        foreach ($variations as $variation) {
            if (!is_array($variation) || empty($variation['name'])) {
                continue;
            }

            $labels = $variation['values']['label'] ?? [];
            if (!is_array($labels)) {
                $labels = [$labels];
            }

            $labels = array_values(array_filter(array_map('strval', $labels)));
            sort($labels);
            $normalized[$variation['name']] = $labels;
        }

        ksort($normalized);

        return $normalized;
    }

    public function hasRewardGroup(Banner $banner): bool
    {
        $groupItems = $banner->relationLoaded('groupItems')
            ? $banner->groupItems
            : $banner->groupItems()->get();

        return $groupItems->where('group_number', 2)->isNotEmpty();
    }

    public function usesSingleGroupDiscount(Banner $banner): bool
    {
        if (!in_array($banner->promotion_type, [
            Banner::PROMOTION_TYPE_PERCENT_OFF,
            Banner::PROMOTION_TYPE_FIXED_AMOUNT,
        ], true)) {
            return false;
        }

        return !$this->hasRewardGroup($banner);
    }

    public function calculateRewardDiscount(Banner $banner, float $rewardLineAmount, Collection $groupTwoItems): float
    {
        if ($rewardLineAmount <= 0) {
            return 0;
        }

        if ($banner->promotion_type === Banner::PROMOTION_TYPE_FIXED_AMOUNT) {
            return min($banner->reward_discount_value, $rewardLineAmount);
        }

        $percent = $this->resolveRewardPercent($banner, $groupTwoItems);

        return min($rewardLineAmount, ($rewardLineAmount * $percent) / 100);
    }

    public function meetsMinimumSpend(Banner $banner, float $cartSubtotal): bool
    {
        if ($banner->promotion_type !== Banner::PROMOTION_TYPE_FIXED_AMOUNT) {
            return true;
        }

        $minimum = (float) ($banner->minimum_spend ?? 0);
        if ($minimum <= 0) {
            return true;
        }

        return $cartSubtotal >= $minimum;
    }

    public function resolveRewardPercent(Banner $banner, Collection $groupTwoItems): float
    {
        if ($banner->promotion_type === Banner::PROMOTION_TYPE_FIXED_AMOUNT) {
            return 0;
        }

        return (float) ($banner->reward_discount_value ?? 0);
    }

    public function shouldChargeAddons(Banner $banner, ?string $role): bool
    {
        if ($role === 'paid') {
            return (bool) $banner->charge_paid_addons;
        }

        if ($role === 'reward') {
            return (bool) $banner->charge_reward_addons;
        }

        return true;
    }

    public function findMatchingGroupItem(Banner $banner, int $groupNumber, array $cartLine): ?BannerGroupItem
    {
        $productId = (int) ($cartLine['product_id'] ?? 0);
        $variations = $cartLine['variations'] ?? [];

        return $banner->groupItems
            ->where('group_number', $groupNumber)
            ->first(function (BannerGroupItem $item) use ($productId, $variations) {
                return (int) $item->product_id === $productId
                    && $this->variationsMatch($item->variations ?? [], $variations);
            });
    }

    /**
     * Variation price included when add-ons/modifiers are not charged.
     * Single-choice variations (e.g. size) and admin-preset options only.
     */
    public function computePromoCoreVariationPrice(
        array $productVariations,
        array $cartVariations,
        array $presetVariations
    ): float {
        $presetKeys = $this->buildPresetVariationKeys($presetVariations);
        $includedVariationPrice = 0;

        foreach ($cartVariations as $cartVariation) {
            if (!is_array($cartVariation) || empty($cartVariation['name'])) {
                continue;
            }

            $productVariation = collect($productVariations)->firstWhere('name', $cartVariation['name']);
            if (!$productVariation) {
                continue;
            }

            $selectedLabels = $cartVariation['values']['label'] ?? [];
            if (!is_array($selectedLabels)) {
                $selectedLabels = [$selectedLabels];
            }

            $labelCounts = [];
            foreach ($selectedLabels as $selectedLabel) {
                if ($selectedLabel === null || $selectedLabel === '') {
                    continue;
                }
                $labelKey = (string) $selectedLabel;
                $labelCounts[$labelKey] = ($labelCounts[$labelKey] ?? 0) + 1;
            }

            $variationType = $productVariation['type'] ?? 'single';
            $isSingleChoice = $variationType === 'single';

            foreach ($productVariation['values'] ?? [] as $option) {
                $label = $option['label'] ?? null;
                if ($label === null) {
                    continue;
                }

                $labelKey = (string) $label;
                $count = (int) ($labelCounts[$labelKey] ?? 0);
                if ($count <= 0) {
                    continue;
                }

                $key = $cartVariation['name'] . '::' . $label;
                if ($isSingleChoice || isset($presetKeys[$key])) {
                    $includedVariationPrice += (float) ($option['optionPrice'] ?? 0) * $count;
                }
            }
        }

        return $includedVariationPrice;
    }

    public function resolvePromoUnitPrice(
        Banner $banner,
        ?string $promotionRole,
        array $cartLine,
        float $basePrice,
        float $fullVariationPrice,
        array $productVariations
    ): float {
        if (!$promotionRole || $this->shouldChargeAddons($banner, $promotionRole)) {
            return $basePrice + $fullVariationPrice;
        }

        $groupNumber = $promotionRole === 'reward' ? 2 : 1;
        $groupItem = $this->findMatchingGroupItem($banner, $groupNumber, $cartLine);
        $coreVariationPrice = $this->computePromoCoreVariationPrice(
            $productVariations,
            $cartLine['variations'] ?? [],
            $groupItem?->variations ?? []
        );

        return $basePrice + $coreVariationPrice;
    }

    /**
     * Line amount the promotion discount applies to when add-ons/modifiers are not charged.
     * Includes base price + single-choice variations (e.g. size) + admin-preset variations.
     */
    public function computePromoDiscountableLineAmount(
        float $basePrice,
        array $productVariations,
        array $cartVariations,
        array $presetVariations,
        array $discountData,
        int $quantity = 1
    ): float {
        $includedVariationPrice = $this->computePromoCoreVariationPrice(
            $productVariations,
            $cartVariations,
            $presetVariations
        );

        $priceWithIncludedVariations = $basePrice + $includedVariationPrice;
        $discountedPerUnit = max(
            0,
            $priceWithIncludedVariations - Helpers::discount_calculate($discountData, $priceWithIncludedVariations)
        );

        return $discountedPerUnit * $quantity;
    }

    private function buildPresetVariationKeys(array $presetVariations): array
    {
        $keys = [];

        foreach ($presetVariations as $variation) {
            if (!is_array($variation) || empty($variation['name'])) {
                continue;
            }

            $labels = $variation['values']['label'] ?? [];
            if (!is_array($labels)) {
                $labels = [$labels];
            }

            foreach ($labels as $label) {
                $keys[$variation['name'] . '::' . (string) $label] = true;
            }
        }

        return $keys;
    }

    public function recordUsage(Banner $banner, int|string|null $userId, int $isGuest, int|string $orderId): void
    {
        PromotionUsage::create([
            'banner_id' => $banner->id,
            'user_id' => $isGuest === 0 ? $userId : null,
            'guest_id' => $isGuest === 1 ? $userId : null,
            'order_id' => $orderId,
        ]);

        $banner->increment('usage_count');
    }

    public function formatBannerForApi(Banner $banner, int $branchId): array
    {
        $groupOne = [];
        $groupTwo = [];
        $groupItems = Schema::hasTable('banner_group_items')
            ? ($banner->relationLoaded('groupItems') ? $banner->groupItems : $banner->groupItems()->get())
            : collect();

        foreach ($groupItems as $groupItem) {
            $product = $groupItem->product;
            if (!$product) {
                continue;
            }

            $formattedProduct = Helpers::product_data_formatting($product, false);
            $entry = [
                'id' => $groupItem->id,
                'product_id' => $groupItem->product_id,
                'variations' => $groupItem->variations ?? [],
                'sort_order' => $groupItem->sort_order,
                'product' => $formattedProduct,
            ];

            if ((int) $groupItem->group_number === 1) {
                $groupOne[] = $entry;
            } else {
                $groupTwo[] = $entry;
            }
        }

        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'headline' => $banner->headline ?? $banner->title,
            'description' => $banner->description,
            'image' => $banner->image,
            'promotion_type' => $banner->promotion_type ?? Banner::PROMOTION_TYPE_BOGO,
            'reward_discount_value' => $banner->reward_discount_value,
            'minimum_spend' => $banner->minimum_spend,
            'charge_paid_addons' => $banner->charge_paid_addons,
            'charge_reward_addons' => $banner->charge_reward_addons,
            'order_type_mode' => $banner->order_type_mode,
            'order_types' => $banner->order_types,
            'payment_methods' => $banner->payment_methods,
            'once_per_customer' => $banner->once_per_customer,
            'customer_eligibility' => $banner->customer_eligibility ?? 'any',
            'max_reward_qty' => $banner->max_reward_qty,
            'start_date' => $banner->start_date?->toIso8601String(),
            'end_date' => $banner->end_date?->toIso8601String(),
            'group_1' => $groupOne,
            'group_2' => $groupTwo,
        ];
    }
}
