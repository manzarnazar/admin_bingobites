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

        if ($banner->promotion_type === Banner::PROMOTION_TYPE_FIXED_AMOUNT) {
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
        ?Banner $banner,
        ?string $promotionRole,
        float $lineNet,
        float $coreAmount,
        float $addonCost,
        float $promoLineDiscount
    ): array {
        if ($banner && $banner->promotion_type === Banner::PROMOTION_TYPE_FIXED_AMOUNT) {
            $roleSuffix = self::buildPromoEmailSuffix($banner, $promotionRole, $coreAmount, 0.0);

            return array_merge(
                $item,
                self::buildLinePriceDisplayFields($coreAmount, $coreAmount, 0.0, 0.0, false),
                [
                    'promotion_role' => $promotionRole,
                    'core_amount' => $coreAmount,
                    'addon_cost' => $addonCost,
                    'promo_line_discount' => 0.0,
                    'promo_label_suffix' => $roleSuffix,
                    'email_label' => $item['email_label'] . $roleSuffix,
                ]
            );
        }

        // PRICE column: core only (base + size/variation after promo). Add-ons live in footer row.
        $displayTotal = max(0, $coreAmount - $promoLineDiscount);
        $isFullyDiscounted = $coreAmount > 0 && $promoLineDiscount >= $coreAmount - 0.01;
        $showFreeLabel = $isFullyDiscounted;

        $roleSuffix = $banner
            ? self::buildPromoEmailSuffix($banner, $promotionRole, $coreAmount, $promoLineDiscount)
            : '';

        return array_merge(
            $item,
            self::buildLinePriceDisplayFields($coreAmount, $displayTotal, $promoLineDiscount, 0.0, $showFreeLabel),
            [
                'promotion_role' => $promotionRole,
                'core_amount' => $coreAmount,
                'addon_cost' => $addonCost,
                'promo_line_discount' => $promoLineDiscount,
                'promo_label_suffix' => $roleSuffix,
                'email_label' => $item['email_label'] . $roleSuffix,
            ]
        );
    }

    public static function buildPromoEmailSuffix(
        Banner $banner,
        ?string $promotionRole,
        float $coreAmount,
        float $promoLineDiscount
    ): string {
        if (!$promotionRole) {
            return '';
        }

        $isFullyDiscounted = $coreAmount > 0 && $promoLineDiscount >= $coreAmount - 0.01;

        if ($isFullyDiscounted) {
            return ' (FREE ITEM)';
        }

        if ($promotionRole === 'paid' && $promoLineDiscount <= 0.01) {
            return ' (PAID ITEM)';
        }

        if ($promoLineDiscount <= 0.01) {
            return '';
        }

        if ($banner->promotion_type === Banner::PROMOTION_TYPE_FIXED_AMOUNT) {
            return $promotionRole === 'paid' ? ' (PAID ITEM)' : '';
        }

        $percent = $banner->promotion_type === Banner::PROMOTION_TYPE_BOGO
            ? 100.0
            : (float) ($banner->reward_discount_value ?? 0);
        $pctLabel = abs($percent - round($percent)) < 0.01
            ? (string) (int) round($percent)
            : rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        return " ({$pctLabel}% - off)";
    }

    public static function computeMailCoreAmount(
        PromoOrderService $promoService,
        float $basePrice,
        array $productVariations,
        array $cartVariations,
        array $discountData,
        int $quantity = 1,
        array $presetVariations = []
    ): float {
        return $promoService->computePromoDiscountableLineAmount(
            $basePrice,
            $productVariations,
            $cartVariations,
            $presetVariations,
            $discountData,
            $quantity
        );
    }

    public static function finalizeNonPromoLineDisplay(array $item): array
    {
        $coreAmount = (float) ($item['core_amount'] ?? $item['line_price']);
        $productDiscount = (float) ($item['product_discount'] ?? 0);

        return array_merge(
            $item,
            self::buildLinePriceDisplayFields($coreAmount, $coreAmount, 0.0, $productDiscount, false),
            [
                'promotion_role' => null,
                'core_amount' => $coreAmount,
                'promo_line_discount' => 0.0,
            ]
        );
    }

    public static function computeDisplayGst(float $totalPaid): float
    {
        return floor(max(0, $totalPaid) * 10) / 100;
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildLinePriceDisplayFields(
        float $coreAmount,
        float $displayTotal,
        float $promoLineDiscount,
        float $productDiscount,
        bool $showFreeLabel,
        ?callable $formatCurrency = null
    ): array {
        $formatCurrency ??= function (float $amount): string {
            if (defined('CACHE_BUSINESS_SETTINGS_TABLE')) {
                return Helpers::set_symbol($amount);
            }

            return '$' . number_format($amount, 2, '.', '');
        };

        $displayOriginalTotal = $coreAmount;
        if ($promoLineDiscount <= 0.01 && $productDiscount > 0.01) {
            $displayOriginalTotal = $coreAmount + $productDiscount;
        }

        $showPriceStrikethrough = $displayOriginalTotal > $displayTotal + 0.01;

        return [
            'display_total' => $displayTotal,
            'display_original_total' => $displayOriginalTotal,
            'display_total_formatted' => $formatCurrency($displayTotal),
            'display_original_total_formatted' => $formatCurrency($displayOriginalTotal),
            'show_free_label' => $showFreeLabel,
            'show_price_strikethrough' => $showPriceStrikethrough,
        ];
    }

    public static function buildPromotionLabel(Banner $banner): string
    {
        $name = $banner->headline ?? $banner->title ?? 'Promotion';

        if ($banner->promotion_type === Banner::PROMOTION_TYPE_BOGO) {
            return "Discount ({$name} – Free item)";
        }

        return "Discount ({$name})";
    }

    /**
     * @param  array<int, array<string, mixed>>  $productAddOns
     * @return array<int, array{qty: int, group_name: ?string, name: string, unit_price: float, display: string}>
     */
    public static function buildMailAddonLines(
        object $detail,
        array $productDetails,
        array $productVariations
    ): array {
        $merged = [];
        $productAddOns = self::normalizeProductAddOns($productDetails);

        $addOnIds = json_decode($detail->add_on_ids ?? '[]', true) ?: [];
        $addOnQtys = json_decode($detail->add_on_qtys ?? '[]', true) ?: [];
        $addOnPrices = json_decode($detail->add_on_prices ?? '[]', true) ?: [];

        foreach ($addOnIds as $key => $id) {
            $addonName = self::resolveAddOnName((int) $id, $productAddOns);
            $qty = max(1, (int) ($addOnQtys[$key] ?? 1));
            $unitPrice = (float) ($addOnPrices[$key] ?? 0);

            self::mergeAddonLine($merged, [
                'qty' => $qty,
                'group_name' => null,
                'name' => $addonName,
                'unit_price' => $unitPrice,
            ]);
        }

        $storedVariations = json_decode($detail->variation ?? '[]', true) ?: [];
        foreach ($storedVariations as $variation) {
            if (!is_array($variation) || empty($variation['name'])) {
                continue;
            }

            $groupName = (string) $variation['name'];
            foreach ($variation['values'] ?? [] as $value) {
                if (!is_array($value)) {
                    continue;
                }

                if (!self::isAddonVariationOption($productVariations, $productAddOns, $groupName, $value)) {
                    continue;
                }

                $label = trim((string) ($value['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                self::mergeAddonLine($merged, [
                    'qty' => max(1, (int) ($value['qty'] ?? 1)),
                    'group_name' => $groupName,
                    'name' => $label,
                    'unit_price' => (float) ($value['optionPrice'] ?? 0),
                ]);
            }
        }

        return array_values($merged);
    }

    /**
     * @param  array<int, array{qty: int, group_name: ?string, name: string, unit_price: float}>  $lines
     * @return array<int, array{qty: int, group_name: ?string, name: string, unit_price: float, display: string}>
     */
    public static function formatAddonLinesForDisplay(array $lines): array
    {
        return array_map(
            fn (array $line) => array_merge($line, [
                'display' => self::formatMailAddonLineDisplay($line),
            ]),
            $lines
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $merged
     * @param  array{qty: int, group_name: ?string, name: string, unit_price: float}  $line
     */
    private static function mergeAddonLine(array &$merged, array $line): void
    {
        $key = mb_strtolower(trim($line['name']));
        if ($key === '') {
            return;
        }

        if (!isset($merged[$key])) {
            $merged[$key] = $line;

            return;
        }

        $merged[$key]['qty'] += $line['qty'];
        if ($merged[$key]['unit_price'] <= 0 && $line['unit_price'] > 0) {
            $merged[$key]['unit_price'] = $line['unit_price'];
        }
        if (!$merged[$key]['group_name'] && $line['group_name']) {
            $merged[$key]['group_name'] = $line['group_name'];
        }
    }

    /**
     * @param  array{qty: int, group_name: ?string, name: string, unit_price: float}  $line
     */
    public static function formatMailAddonLineDisplay(
        array $line,
        ?callable $formatCurrency = null
    ): string {
        $qty = max(1, (int) ($line['qty'] ?? 1));
        $name = (string) ($line['name'] ?? 'Add-on');
        $formatCurrency ??= fn (float $amount) => Helpers::set_symbol($amount);
        $priceText = $formatCurrency((float) ($line['unit_price'] ?? 0));
        $groupName = trim((string) ($line['group_name'] ?? ''));

        if ($groupName !== '') {
            return "{$qty} x ({$name} - {$priceText})";
        }

        return "{$qty} x {$name} - {$priceText}";
    }

    public static function buildNonAddonVariationText(
        ?string $variationJson,
        array $productDetails,
        array $productVariations
    ): string {
        if (!$variationJson) {
            return '';
        }

        $variations = json_decode($variationJson, true);
        if (!is_array($variations) || count($variations) === 0) {
            return '';
        }

        $productAddOns = self::normalizeProductAddOns($productDetails);
        $parts = [];

        foreach ($variations as $variation) {
            if (!is_array($variation) || empty($variation['name'])) {
                continue;
            }

            $groupName = (string) $variation['name'];
            foreach ($variation['values'] ?? [] as $value) {
                if (!is_array($value)) {
                    continue;
                }

                if (self::isAddonVariationOption($productVariations, $productAddOns, $groupName, $value)) {
                    continue;
                }

                $label = trim((string) ($value['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                $qty = max(1, (int) ($value['qty'] ?? 1));
                $parts[] = $qty > 1 ? "{$label} x{$qty}" : $label;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $productAddOns
     */
    public static function isAddonVariationOption(
        array $productVariations,
        array $productAddOns,
        string $groupName,
        array $option
    ): bool {
        if (self::isAddonVariationGroupName($groupName)) {
            return true;
        }

        $label = mb_strtolower(trim((string) ($option['label'] ?? '')));
        if ($label !== '') {
            foreach ($productAddOns as $addon) {
                $addonName = mb_strtolower(trim((string) ($addon['name'] ?? '')));
                if ($addonName === '') {
                    continue;
                }

                if ($label === $addonName || str_contains($label, $addonName)) {
                    return true;
                }
            }
        }

        $productVariation = collect($productVariations)->firstWhere('name', $groupName);
        if (($productVariation['type'] ?? '') === 'single') {
            return false;
        }

        return ((float) ($option['optionPrice'] ?? 0)) > 0;
    }

    private static function isAddonVariationGroupName(string $groupName): bool
    {
        $normalized = mb_strtolower(trim($groupName));

        return str_contains($normalized, 'addon')
            || str_contains($normalized, 'add-on')
            || str_contains($normalized, 'add on');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeProductAddOns(array $productDetails): array
    {
        $addOns = $productDetails['add_ons'] ?? [];

        if (is_string($addOns)) {
            $addOns = json_decode($addOns, true) ?: [];
        }

        return is_array($addOns) ? $addOns : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $productAddOns
     */
    private static function resolveAddOnName(int $addOnId, array $productAddOns): string
    {
        foreach ($productAddOns as $addon) {
            if ((int) ($addon['id'] ?? 0) === $addOnId) {
                return (string) ($addon['name'] ?? 'Add-on');
            }
        }

        return 'Add-on';
    }
}
