<?php

namespace App\CentralLogics;

use App\Model\AddOn;
use App\Model\Banner;
use App\Model\CustomerAddress;
use App\Model\Order;
use App\Services\PromoOrderService;
use Carbon\Carbon;

class BingoBitesOrderMailHelper
{
    public const BRAND_RED = '#E31E24';
    public const ASSET_DIR = 'assets/email/bingo-bites';

    public static function iconPaths(): array
    {
        $dir = public_path(self::ASSET_DIR . '/icons');

        return [
            'customer' => $dir . '/customer.png',
            'store' => $dir . '/store.png',
            'order_details' => $dir . '/order-details.png',
            'location' => $dir . '/location.png',
        ];
    }

    public static function iconCids(): array
    {
        return [
            'customer' => 'icon_customer',
            'store' => 'icon_store',
            'order_details' => 'icon_order_details',
            'location' => 'icon_location',
        ];
    }

    public static function build(int $orderId): array
    {
        $order = Order::with([
            'details.product',
            'customer',
            'branch',
            'table',
            'order_partial_payments',
            'order_change_amount',
        ])->findOrFail($orderId);

        $address = $order->delivery_address
            ?? ($order->delivery_address_id ? CustomerAddress::find($order->delivery_address_id) : null);
        $order->address = $address;

        $banner = null;
        if ($order->promotion_id) {
            $banner = Banner::with('groupItems')->find($order->promotion_id);
        }

        $lineItems = self::buildLineItems($order, $banner);
        $totals = self::buildTotals($order, $lineItems, $banner);
        $customer = self::buildCustomerBlock($order, $address);
        $location = self::buildLocationBlock($order, $address);
        $store = self::buildStoreBlock($order);
        $preparationMinutes = (int) ($order->preparation_time
            ?: $order->branch?->preparation_time
            ?: Helpers::get_business_settings('default_preparation_time')
            ?: 30);

        $readyAt = Carbon::parse($order->created_at)->addMinutes($preparationMinutes);

        $headerPath = public_path(self::ASSET_DIR . '/header.png');
        $logoPath = public_path(self::ASSET_DIR . '/logo.png');

        return [
            'order' => $order,
            'brand_red' => self::BRAND_RED,
            'header_path' => $headerPath,
            'logo_path' => $logoPath,
            'icons' => self::iconPaths(),
            'icon_cids' => self::iconCids(),
            'order_type_label' => self::orderTypeLabel($order->order_type),
            'order_date' => Carbon::parse($order->created_at)->format('d/m/Y'),
            'order_date_long' => Carbon::parse($order->created_at)->format('d M Y'),
            'order_time' => Carbon::parse($order->created_at)->format('h:i a'),
            'order_time_pdf' => Carbon::parse($order->created_at)->format('h:i A'),
            'estimated_ready_time' => $readyAt->format('h:i a'),
            'customer' => $customer,
            'location' => $location,
            'store' => $store,
            'line_items' => $lineItems,
            'totals' => $totals,
            'company_name' => Helpers::get_business_settings('restaurant_name') ?? 'Bingo Bites',
        ];
    }

    public static function orderTypeLabel(?string $orderType): string
    {
        return match ($orderType) {
            'pos', 'take_away' => 'Take Away',
            'delivery' => 'Delivery',
            'dine_in' => 'Dine In',
            default => ucfirst(str_replace('_', ' ', (string) $orderType)),
        };
    }

    private static function buildCustomerBlock(Order $order, $address): array
    {
        if ($order->is_guest == 0 && $order->customer) {
            return [
                'name' => trim(($order->customer->f_name ?? '') . ' ' . ($order->customer->l_name ?? '')),
                'email' => $order->customer->email ?? '',
                'phone' => $order->customer->phone ?? '',
            ];
        }

        if ($address) {
            $addr = is_array($address) ? $address : $address->toArray();

            return [
                'name' => $addr['contact_person_name'] ?? 'Guest',
                'email' => $addr['email'] ?? '',
                'phone' => $addr['contact_person_number'] ?? '',
            ];
        }

        return [
            'name' => 'Guest',
            'email' => '',
            'phone' => '',
        ];
    }

    private static function buildLocationBlock(Order $order, $address): array
    {
        $branch = $order->branch;
        $orderType = $order->order_type;

        if (in_array($orderType, ['pos', 'take_away'], true)) {
            return [
                'title' => 'Collection Location',
                'name' => $branch?->name ?? Helpers::get_business_settings('restaurant_name'),
                'address' => $branch?->address ?? Helpers::get_business_settings('address'),
                'phone' => $branch?->phone ?? Helpers::get_business_settings('phone'),
            ];
        }

        if ($orderType === 'delivery') {
            $addr = is_array($address) ? $address : ($address?->toArray() ?? []);

            return [
                'title' => 'Delivery Address',
                'name' => $addr['contact_person_name'] ?? self::buildCustomerBlock($order, $address)['name'],
                'address' => $addr['address'] ?? '',
                'phone' => $addr['contact_person_number'] ?? '',
            ];
        }

        $tableLabel = $order->table?->number ?? $order->table_id;
        $branchAddress = $branch?->address ?? Helpers::get_business_settings('address');

        return [
            'title' => 'Restaurant Location',
            'name' => $branch?->name ?? Helpers::get_business_settings('restaurant_name'),
            'address' => $tableLabel
                ? $branchAddress . (str_contains((string) $branchAddress, 'Table') ? '' : ' (Table ' . $tableLabel . ')')
                : $branchAddress,
            'phone' => $branch?->phone ?? Helpers::get_business_settings('phone'),
        ];
    }

    private static function buildStoreBlock(Order $order): array
    {
        $branch = $order->branch;
        $website = 'order.bingobites.com.au';

        return [
            'name' => $branch?->name ?? Helpers::get_business_settings('restaurant_name') ?? 'Bingo Bites',
            'address' => $branch?->address ?? Helpers::get_business_settings('address') ?? '',
            'phone' => $branch?->phone ?? Helpers::get_business_settings('phone') ?? '',
            'email' => $branch?->email ?? Helpers::get_business_settings('email_address') ?? '',
            'website' => $website,
        ];
    }

    private static function buildLineItems(Order $order, ?Banner $banner): array
    {
        $items = [];

        foreach ($order->details as $detail) {
            if (!$detail->product && !$detail->product_details) {
                continue;
            }

            $productDetails = json_decode($detail->product_details, true);
            $name = $detail->product?->name ?? ($productDetails['name'] ?? 'Item');
            $addOnQtys = json_decode($detail->add_on_qtys, true) ?? [];
            $addOnPrices = json_decode($detail->add_on_prices, true) ?? [];
            $addOnTaxes = json_decode($detail->add_on_taxes, true) ?? [];
            $addOnIds = json_decode($detail->add_on_ids, true) ?? [];

            $variationText = self::formatVariationText($detail->variation);
            $addonParts = [];
            $lineAddonCost = 0.0;
            $lineAddonTax = 0.0;

            foreach ($addOnIds as $key => $id) {
                $addon = AddOn::find($id);
                $qty = $addOnQtys[$key] ?? 1;
                $price = (float) ($addOnPrices[$key] ?? 0);
                $tax = (float) ($addOnTaxes[$key] ?? 0);
                $addonParts[] = ($addon?->name ?? 'Add-on') . ($qty > 1 ? " x{$qty}" : '');
                $lineAddonCost += $price * $qty;
                $lineAddonTax += $tax * $qty;
            }

            $grossLine = (float) $detail->price * (int) $detail->quantity;
            $productDiscount = (float) $detail->discount_on_product * (int) $detail->quantity;
            $netLine = ($detail->price - $detail->discount_on_product) * (int) $detail->quantity;
            $lineTax = (float) $detail->tax_amount * (int) $detail->quantity;

            $items[] = [
                'quantity' => (int) $detail->quantity,
                'name' => $name,
                'display_name' => ucwords(strtolower($name)),
                'variation_text' => $variationText,
                'addon_text' => implode(', ', $addonParts),
                'display_detail' => trim(implode(', ', array_filter([$variationText, implode(', ', $addonParts)]))),
                'gross_price' => $grossLine,
                'product_discount' => $productDiscount,
                'line_price' => $netLine,
                'addon_cost' => $lineAddonCost,
                'tax' => $lineTax + $lineAddonTax,
                'email_label' => (int) $detail->quantity . ' x ' . $name,
                '_detail' => $detail,
            ];
        }

        if (!$banner) {
            return self::applyNonPromoLinePricing($items);
        }

        return self::applyPromoLinePricing($items, $banner, $order);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function applyNonPromoLinePricing(array $items): array
    {
        $promoService = app(PromoOrderService::class);
        $pricedItems = [];

        foreach ($items as $item) {
            /** @var object $detail */
            $detail = $item['_detail'];
            unset($item['_detail']);

            $productDetails = json_decode($detail->product_details, true) ?: [];
            $basePrice = (float) ($productDetails['price'] ?? $detail->product?->price ?? 0);
            $productVariations = self::resolveProductVariationsForDetail($detail, $productDetails);
            $cartLine = PromoMailPricing::buildCartLineFromDetail($detail);
            $discountData = [
                'discount_type' => $productDetails['discount_type'] ?? 'percent',
                'discount' => $productDetails['discount'] ?? 0,
            ];

            $coreAmount = PromoMailPricing::computeMailCoreAmount(
                $promoService,
                $basePrice,
                $productVariations,
                $cartLine['variations'],
                $discountData,
                (int) $detail->quantity
            );

            $lineNet = (float) $item['line_price'];
            $explicitAddonCost = (float) $item['addon_cost'];
            $variationAddonCost = 0.0;
            if (empty(json_decode($detail->add_on_ids, true) ?? [])) {
                $variationAddonCost = max(0, $lineNet - $coreAmount);
            }

            $pricedItems[] = array_merge($item, [
                'core_amount' => $coreAmount,
                'addon_cost' => $explicitAddonCost + $variationAddonCost,
            ]);
        }

        return array_map(
            fn (array $item) => PromoMailPricing::finalizeNonPromoLineDisplay($item),
            $pricedItems
        );
    }

    /**
     * @param  array<string, mixed>  $productDetails
     */
    private static function resolveProductVariationsForDetail(object $detail, array $productDetails): array
    {
        if ($detail->product) {
            return Helpers::resolveOrderProductVariations($detail->product, null);
        }

        $productVariationsRaw = $productDetails['variations'] ?? '[]';

        return is_array($productVariationsRaw)
            ? $productVariationsRaw
            : (json_decode($productVariationsRaw, true) ?: []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function applyPromoLinePricing(array $items, Banner $banner, Order $order): array
    {
        $promoService = app(PromoOrderService::class);
        $pricedItems = [];

        foreach ($items as $item) {
            /** @var object $detail */
            $detail = $item['_detail'];
            unset($item['_detail']);

            $promotionRole = $detail->promotion_role ?? null;
            $quantity = (int) $detail->quantity;
            $lineNet = (float) $item['line_price'];
            $explicitAddonCost = (float) $item['addon_cost'];

            $productDetails = json_decode($detail->product_details, true) ?: [];
            $basePrice = (float) ($productDetails['price'] ?? $detail->product?->price ?? 0);
            $productVariations = self::resolveProductVariationsForDetail($detail, $productDetails);

            $cartLine = PromoMailPricing::buildCartLineFromDetail($detail);
            $cartVariations = $cartLine['variations'];

            $roleDiscountData = PromoMailPricing::discountDataForRole($promotionRole);
            $discountData = $roleDiscountData ?: [
                'discount_type' => $productDetails['discount_type'] ?? 'percent',
                'discount' => $productDetails['discount'] ?? 0,
            ];

            $presetVariations = $promotionRole
                ? PromoMailPricing::resolvePresetVariations($banner, $promoService, $promotionRole, $cartLine)
                : [];

            $coreAmount = $promotionRole
                ? $promoService->computePromoDiscountableLineAmount(
                    $basePrice,
                    $productVariations,
                    $cartVariations,
                    $presetVariations,
                    $discountData,
                    $quantity
                )
                : PromoMailPricing::computeMailCoreAmount(
                    $promoService,
                    $basePrice,
                    $productVariations,
                    $cartVariations,
                    $discountData,
                    $quantity
                );

            $variationAddonCost = 0.0;
            if (empty($addOnIds = json_decode($detail->add_on_ids, true) ?? [])) {
                $variationAddonCost = max(0, $lineNet - $coreAmount);
            }

            $addonCost = $explicitAddonCost + $variationAddonCost;

            if ($promotionRole && !$promoService->shouldChargeAddons($banner, $promotionRole)) {
                $addonCost = 0.0;
            }

            $rawPromoDiscount = PromoMailPricing::computeRawPromoLineDiscount(
                $banner,
                $promoService,
                $promotionRole,
                $coreAmount,
                $cartLine
            );

            $pricedItems[] = array_merge($item, [
                'addon_cost' => $addonCost,
                'core_amount' => $coreAmount,
                '_raw_promo_discount' => $rawPromoDiscount,
                '_line_net' => $lineNet,
                'promotion_role' => $promotionRole,
            ]);
        }

        $storedPromotionDiscount = (float) ($order->promotion_discount_amount ?? 0);
        $pricedItems = PromoMailPricing::allocatePromoLineDiscounts($pricedItems, $storedPromotionDiscount);

        return array_map(function (array $item) {
            $lineNet = (float) ($item['_line_net'] ?? $item['line_price']);
            unset($item['_line_net']);

            return PromoMailPricing::finalizePromoLineDisplay(
                $item,
                $item['promotion_role'] ?? null,
                $lineNet,
                (float) $item['core_amount'],
                (float) $item['addon_cost'],
                (float) ($item['promo_line_discount'] ?? 0)
            );
        }, $pricedItems);
    }

    private static function formatVariationText(?string $variationJson): string
    {
        if (!$variationJson) {
            return '';
        }

        $variations = json_decode($variationJson, true);
        if (!is_array($variations) || count($variations) === 0) {
            return '';
        }

        $parts = [];
        foreach ($variations as $variation) {
            if (isset($variation['name'], $variation['values']) && is_array($variation['values'])) {
                foreach ($variation['values'] as $value) {
                    $parts[] = $value['label'] ?? '';
                }
            } elseif (is_array($variation)) {
                foreach ($variation as $key => $val) {
                    if (is_string($key) && !is_array($val)) {
                        $parts[] = $val;
                    }
                }
            }
        }

        if (empty($parts) && isset($variations[0]) && is_array($variations[0])) {
            foreach ($variations[0] as $val) {
                if (is_string($val)) {
                    $parts[] = $val;
                }
            }
        }

        return implode(', ', array_filter($parts));
    }

    private static function buildTotals(Order $order, array $lineItems, ?Banner $banner): array
    {
        $itemsSubtotal = 0.0;
        $productDiscount = 0.0;
        $addonsCost = 0.0;
        $taxAmount = 0.0;

        foreach ($lineItems as $item) {
            if ($banner) {
                $itemsSubtotal += (float) ($item['core_amount'] ?? 0);
            } else {
                $itemsSubtotal += $item['gross_price'];
            }
            $productDiscount += $item['product_discount'];
            $addonsCost += $item['addon_cost'];
            $taxAmount += $item['tax'];
        }

        $promotionDiscount = (float) ($order->promotion_discount_amount ?? 0);
        $couponDiscount = (float) ($order->coupon_discount_amount ?? 0);
        $extraDiscount = (float) ($order->extra_discount ?? 0);
        $referralDiscount = (float) ($order->referral_discount ?? 0);
        $combinedDiscount = $productDiscount + $couponDiscount + $extraDiscount + $referralDiscount;

        $deliveryFee = 0.0;
        if ($order->order_type === 'delivery') {
            $deliveryFee = (float) ($order->delivery_charge ?? 0);
        }

        $totalPaid = (float) $order->order_amount;

        $totals = [
            'subtotal' => $itemsSubtotal,
            'addons' => $addonsCost,
            'discount' => $combinedDiscount,
            'product_discount' => $productDiscount,
            'promotion_discount' => $promotionDiscount,
            'gst' => $taxAmount,
            'delivery_fee' => $deliveryFee,
            'total_paid' => max(0, $totalPaid),
            'subtotal_formatted' => Helpers::set_symbol($itemsSubtotal),
            'addons_formatted' => Helpers::set_symbol($addonsCost),
            'discount_formatted' => Helpers::set_symbol($combinedDiscount),
            'promotion_discount_formatted' => Helpers::set_symbol($promotionDiscount),
            'gst_formatted' => Helpers::set_symbol($taxAmount),
            'delivery_fee_formatted' => Helpers::set_symbol($deliveryFee),
            'total_paid_formatted' => Helpers::set_symbol(max(0, $totalPaid)),
        ];

        if ($banner && $promotionDiscount > 0) {
            $totals['promotion_label'] = PromoMailPricing::buildPromotionLabel($banner);
        }

        return $totals;
    }
}
