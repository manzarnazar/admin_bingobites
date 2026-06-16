<?php

namespace App\CentralLogics;

use App\Model\AddOn;
use App\Model\CustomerAddress;
use App\Model\Order;
use Carbon\Carbon;

class BingoBitesOrderMailHelper
{
    public const BRAND_RED = '#E31E24';
    public const ASSET_DIR = 'assets/email/bingo-bites';

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

        $lineItems = self::buildLineItems($order);
        $totals = self::buildTotals($order, $lineItems);
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
            'header_src' => self::toDataUri($headerPath),
            'logo_src' => self::toDataUri($logoPath),
            'order_type_label' => self::orderTypeLabel($order->order_type),
            'order_date' => Carbon::parse($order->created_at)->format('d/m/Y'),
            'order_time' => Carbon::parse($order->created_at)->format('h:i a'),
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
            'pos', 'take_away' => 'Takeaway',
            'delivery' => 'Delivery',
            'dine_in' => 'Dine In',
            default => ucfirst(str_replace('_', ' ', (string) $orderType)),
        };
    }

    private static function toDataUri(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
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
        $appUrl = config('app.url', '');
        $website = $appUrl && !str_contains($appUrl, 'localhost') && !str_contains($appUrl, '127.0.0.1')
            ? preg_replace('#^https?://#', '', rtrim($appUrl, '/'))
            : 'www.bingobites.com.au';

        return [
            'name' => $branch?->name ?? Helpers::get_business_settings('restaurant_name') ?? 'Bingo Bites',
            'address' => $branch?->address ?? Helpers::get_business_settings('address') ?? '',
            'phone' => $branch?->phone ?? Helpers::get_business_settings('phone') ?? '',
            'email' => $branch?->email ?? Helpers::get_business_settings('email_address') ?? '',
            'website' => $website,
        ];
    }

    private static function buildLineItems(Order $order): array
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
                'variation_text' => $variationText,
                'addon_text' => implode(', ', $addonParts),
                'display_detail' => trim(implode(', ', array_filter([$variationText, implode(', ', $addonParts)]))),
                'gross_price' => $grossLine,
                'product_discount' => $productDiscount,
                'line_price' => $netLine,
                'addon_cost' => $lineAddonCost,
                'tax' => $lineTax + $lineAddonTax,
                'email_label' => (int) $detail->quantity . ' x ' . $name,
            ];
        }

        return $items;
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

    private static function buildTotals(Order $order, array $lineItems): array
    {
        $itemsSubtotal = 0.0;
        $productDiscount = 0.0;
        $netSubtotal = 0.0;
        $addonsCost = 0.0;
        $taxAmount = 0.0;

        foreach ($lineItems as $item) {
            $itemsSubtotal += $item['gross_price'];
            $productDiscount += $item['product_discount'];
            $netSubtotal += $item['line_price'];
            $addonsCost += $item['addon_cost'];
            $taxAmount += $item['tax'];
        }

        $couponDiscount = (float) ($order->coupon_discount_amount ?? 0);
        $extraDiscount = (float) ($order->extra_discount ?? 0);
        $referralDiscount = (float) ($order->referral_discount ?? 0);
        $combinedDiscount = $productDiscount + $couponDiscount + $extraDiscount + $referralDiscount;

        $deliveryFee = 0.0;
        if ($order->order_type === 'delivery') {
            $deliveryFee = (float) ($order->delivery_charge ?? 0);
        }

        $totalPaid = $netSubtotal + $addonsCost + $taxAmount + $deliveryFee - $couponDiscount - $extraDiscount - $referralDiscount;

        return [
            'subtotal' => $itemsSubtotal,
            'addons' => $addonsCost,
            'discount' => $combinedDiscount,
            'gst' => $taxAmount,
            'delivery_fee' => $deliveryFee,
            'total_paid' => max(0, $totalPaid),
            'subtotal_formatted' => Helpers::set_symbol($itemsSubtotal),
            'addons_formatted' => Helpers::set_symbol($addonsCost),
            'discount_formatted' => Helpers::set_symbol($combinedDiscount),
            'gst_formatted' => Helpers::set_symbol($taxAmount),
            'delivery_fee_formatted' => Helpers::set_symbol($deliveryFee),
            'total_paid_formatted' => Helpers::set_symbol(max(0, $totalPaid)),
        ];
    }
}
