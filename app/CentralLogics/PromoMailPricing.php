<?php

namespace App\CentralLogics;

use App\Model\Banner;
use App\Services\PromoOrderService;

class PromoMailPricing
{
    public static function storedVariationsToCartFormat(?array $storedVariations): array
    {
        if (!$storedVariations) {
            return [];
        }

        $cart = [];
        foreach ($storedVariations as $variation) {
            if (!is_array($variation) || empty($variation['name'])) {
                continue;
            }

            $labels = [];
            foreach ($variation['values'] ?? [] as $value) {
                if (!is_array($value)) {
                    continue;
                }

                $label = $value['label'] ?? null;
                if ($label === null || $label === '') {
                    continue;
                }

                $qty = max(1, (int) ($value['qty'] ?? 1));
                for ($i = 0; $i < $qty; $i++) {
                    $labels[] = $label;
                }
            }

            $cart[] = [
                'name' => $variation['name'],
                'values' => ['label' => $labels],
            ];
        }

        return $cart;
    }

    public static function buildCartLineFromDetail(object $detail): array
    {
        $storedVariations = json_decode($detail->variation ?? '[]', true) ?: [];

        return [
            'product_id' => (int) $detail->product_id,
            'quantity' => (int) $detail->quantity,
            'variations' => self::storedVariationsToCartFormat($storedVariations),
        ];
    }

    public static function eligiblePromoDiscountRole(Banner $banner, PromoOrderService $promoService): string
    {
        return $promoService->usesSingleGroupDiscount($banner) ? 'paid' : 'reward';
    }

    public static function resolvePresetVariations(
        Banner $banner,
        PromoOrderService $promoService,
        string $promotionRole,
        array $cartLine
    ): array {
        $groupNumber = $promotionRole === 'reward' ? 2 : 1;
        $groupItem = $promoService->findMatchingGroupItem($banner, $groupNumber, $cartLine);

        return $groupItem?->variations ?? [];
    }

    public static function discountDataForRole(?string $promotionRole): array
    {
        if ($promotionRole === 'reward') {
            return [
                'discount_type' => 'amount',
                'discount' => 0,
            ];
        }

        return [];
    }

    public static function computeRawPromoLineDiscount(
        Banner $banner,
        PromoOrderService $promoService,
        ?string $promotionRole,
        float $coreAmount,
        array $cartLine
    ): float {
        if (!$promotionRole || $coreAmount <= 0) {
            return 0.0;
        }

        $eligibleRole = self::eligiblePromoDiscountRole($banner, $promoService);
        if ($promotionRole !== $eligibleRole) {
            return 0.0;
        }

        $groupNumber = $eligibleRole === 'reward' ? 2 : 1;
        $groupItems = $banner->groupItems->where('group_number', $groupNumber);

        return $promoService->calculateRewardDiscount($banner, $coreAmount, $groupItems);
    }

  /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function allocatePromoLineDiscounts(array $items, float $storedPromotionDiscount): array
    {
        $rawTotal = 0.0;
        foreach ($items as $item) {
            $rawTotal += (float) ($item['_raw_promo_discount'] ?? 0);
        }

        foreach ($items as $index => $item) {
            $raw = (float) ($item['_raw_promo_discount'] ?? 0);
            if ($rawTotal > 0 && $storedPromotionDiscount > 0) {
                $items[$index]['promo_line_discount'] = round(
                    $storedPromotionDiscount * ($raw / $rawTotal),
                    2
                );
            } else {
                $items[$index]['promo_line_discount'] = $raw;
            }
            unset($items[$index]['_raw_promo_discount']);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function finalizePromoLineDisplay(
        array $item,
        ?string $promotionRole,
        float $lineNet,
        float $coreAmount,
        float $addonCost,
        float $promoLineDiscount
    ): array {
        // Use core_amount (not lineNet) so variation-priced add-ons baked into
        // stored detail.price are not summed again with addon_cost.
        $displayCore = max(0, $coreAmount - $promoLineDiscount);
        $displayTotal = max(0, $displayCore + $addonCost);
        $showFreeLabel = $promotionRole === 'reward'
            && $promoLineDiscount >= $coreAmount - 0.01
            && $coreAmount > 0;

        $roleSuffix = match ($promotionRole) {
            'paid' => ' (PAID ITEM)',
            'reward' => ' (FREE ITEM)',
            default => '',
        };

        return array_merge($item, [
            'promotion_role' => $promotionRole,
            'core_amount' => $coreAmount,
            'addon_cost' => $addonCost,
            'promo_line_discount' => $promoLineDiscount,
            'display_total' => $displayTotal,
            'show_free_label' => $showFreeLabel,
            'email_label' => $item['email_label'] . $roleSuffix,
        ]);
    }

    public static function finalizeNonPromoLineDisplay(array $item): array
    {
        $displayTotal = (float) $item['line_price'] + (float) $item['addon_cost'];

        return array_merge($item, [
            'promotion_role' => null,
            'core_amount' => (float) $item['line_price'],
            'promo_line_discount' => 0.0,
            'display_total' => $displayTotal,
            'show_free_label' => false,
        ]);
    }

    public static function buildPromotionLabel(Banner $banner): string
    {
        $name = $banner->headline ?? $banner->title ?? 'Promotion';

        if ($banner->promotion_type === Banner::PROMOTION_TYPE_BOGO) {
            return "Discount ({$name} – Free item)";
        }

        return "Discount ({$name})";
    }
}
