<?php

namespace App\Services\Pos;

use App\CentralLogics\Helpers;
use App\Model\AddOn;
use App\Model\Product;
use App\Model\ProductByBranch;
use Illuminate\Support\Facades\Cache;

class PosCartService
{
    private const TTL = 86400;

    public function cacheKey(int $adminId): string
    {
        return 'pos_state_' . $adminId;
    }

    private function nextCartItemKey(array $cart): int
    {
        $numericKeys = [];
        foreach (array_keys($cart) as $key) {
            if (is_int($key)) {
                $numericKeys[] = $key;
            } elseif (is_numeric($key)) {
                $numericKeys[] = (int) $key;
            }
        }

        return empty($numericKeys) ? 0 : (max($numericKeys) + 1);
    }

    public function defaultState(): array
    {
        return [
            'cart' => [],
            'session' => [
                'branch_id' => 1,
                'customer_id' => null,
                'order_type' => 'take_away',
                'table_id' => null,
                'people_number' => null,
                'address' => null,
            ],
        ];
    }

    public function getState(int $adminId): array
    {
        return Cache::get($this->cacheKey($adminId), $this->defaultState());
    }

    public function saveState(int $adminId, array $state): void
    {
        Cache::put($this->cacheKey($adminId), $state, self::TTL);
    }

    public function getSession(int $adminId): array
    {
        return $this->getState($adminId)['session'];
    }

    public function branchId(int $adminId): int
    {
        return (int) ($this->getSession($adminId)['branch_id'] ?? 1);
    }

    public function updateSession(int $adminId, array $data): array
    {
        $state = $this->getState($adminId);
        $branchChanged = false;
        if (isset($data['branch_id'])) {
            $newBranchId = (int) $data['branch_id'];
            $currentBranchId = (int) ($state['session']['branch_id'] ?? 0);
            $branchChanged = $newBranchId !== $currentBranchId;
        }
        $allowed = ['branch_id', 'customer_id', 'order_type', 'table_id', 'people_number', 'address'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $state['session'][$key] = $data[$key];
            }
        }
        if (isset($data['order_type'])) {
            $orderType = $data['order_type'];
            if (in_array($orderType, ['take_away', 'home_delivery'], true)) {
                $state['session']['table_id'] = null;
                $state['session']['people_number'] = null;
            }
            if (in_array($orderType, ['take_away', 'dine_in'], true)) {
                $state['session']['address'] = null;
            }
        }
        if ($branchChanged) {
            $state['cart'] = [];
            $state['session']['table_id'] = null;
            $state['session']['people_number'] = null;
            $state['session']['address'] = null;
        }
        $this->saveState($adminId, $state);
        return $state['session'];
    }

    public function emptyCart(int $adminId, bool $clearSession = true): void
    {
        $state = $this->getState($adminId);
        $state['cart'] = [];
        if ($clearSession) {
            $state['session']['table_id'] = null;
            $state['session']['customer_id'] = null;
            $state['session']['people_number'] = null;
            $state['session']['address'] = null;
            $state['session']['order_type'] = 'take_away';
        }
        $this->saveState($adminId, $state);
    }

    public function addToCart(int $adminId, array $requestData): array
    {
        $product = Product::with('modifierTemplates.items')->findOrFail($requestData['id']);
        $branchId = $this->branchId($adminId);

        $branchProduct = ProductByBranch::where([
            'product_id' => $requestData['id'],
            'branch_id' => $branchId,
        ])->first();

        if (!$branchProduct) {
            return ['error' => true, 'code' => 'not_available', 'message' => translate('Product not available in this branch')];
        }

        $quantity = (int) ($requestData['quantity'] ?? 1);

        if (in_array($branchProduct->stock_type, ['daily', 'fixed'], true)) {
            $availableStock = $branchProduct->stock - $branchProduct->sold_quantity;
            if ($availableStock < $quantity) {
                return [
                    'error' => true,
                    'code' => 'stock_limit',
                    'message' => translate('Product_stock_available_quantity_is_not_enough'),
                ];
            }
        }

        $variations = [];
        $variationPrice = 0;
        $branchProductVariations = $branchProduct->variations;
        $requestVariations = $requestData['variations'] ?? [];

        if ($requestVariations && count($branchProductVariations)) {
            foreach ($requestVariations as $value) {
                if (($value['required'] ?? '') == 'on' && !isset($value['values'])) {
                    return [
                        'error' => true,
                        'code' => 'variation_error',
                        'message' => translate('Please select items from') . ' ' . ($value['name'] ?? ''),
                    ];
                }
                if (isset($value['values']) && ($value['min'] ?? 0) != 0 && ($value['min'] ?? 0) > count($value['values']['label'] ?? [])) {
                    return [
                        'error' => true,
                        'code' => 'variation_error',
                        'message' => translate('Please select minimum ') . $value['min'] . translate(' For ') . ($value['name'] ?? '') . '.',
                    ];
                }
                if (isset($value['values']) && ($value['max'] ?? 0) != 0 && ($value['max'] ?? 0) < count($value['values']['label'] ?? [])) {
                    return [
                        'error' => true,
                        'code' => 'variation_error',
                        'message' => translate('Please select maximum ') . $value['max'] . translate(' For ') . ($value['name'] ?? '') . '.',
                    ];
                }
            }
            $variationData = Helpers::get_varient($branchProductVariations, $requestVariations);
            $variationPrice = $variationData['price'];
            $variations = $requestVariations;
        }

        $discountData = [
            'discount_type' => $branchProduct['discount_type'],
            'discount' => $branchProduct['discount'],
        ];

        $price = $branchProduct['price'] + $variationPrice;
        $discountOnProduct = Helpers::discount_calculate($discountData, $price);

        $selectedAddOnIds = $requestData['addon_id'] ?? [];
        $selectedAddOnQtys = [];
        foreach ($selectedAddOnIds as $index => $addonId) {
            $selectedAddOnQtys[$index] = (int) ($requestData['addon-quantity' . $addonId] ?? 1);
        }
        $normalizedAddons = Helpers::normalize_order_addons(
            product: $product,
            selectedVariations: $variations,
            selectedAddonIds: $selectedAddOnIds,
            selectedAddonQtys: $selectedAddOnQtys,
        );
        $addOnIds = $normalizedAddons['add_on_ids'];
        $addOnQtys = $normalizedAddons['add_on_qtys'];
        $addOnPrices = $normalizedAddons['add_on_prices'];
        $addOnTax = $normalizedAddons['add_on_taxes'];
        $addonPrice = (float) $normalizedAddons['total_add_on_price'];
        $addonTotalTax = (float) $normalizedAddons['add_on_tax_amount'];

        $item = [
            'id' => $product->id,
            'variation_price' => $variationPrice,
            'variations' => $variations,
            'variant' => '',
            'quantity' => $quantity,
            'price' => $price,
            'name' => $product->name,
            'discount' => $discountOnProduct,
            'image' => $product->image,
            'add_ons' => $addOnIds,
            'add_on_qtys' => $addOnQtys,
            'add_on_prices' => $addOnPrices,
            'add_on_tax' => $addOnTax,
            'addon_price' => $addonPrice,
            'addon_total_tax' => $addonTotalTax,
            'discount_data' => $discountData,
        ];

        $state = $this->getState($adminId);
        $nextKey = $this->nextCartItemKey($state['cart']);
        $state['cart'][$nextKey] = $item;
        $this->saveState($adminId, $state);

        return ['error' => false, 'key' => $nextKey, 'item' => $item];
    }

    public function updateQuantity(int $adminId, int $key, int $quantity): bool
    {
        $state = $this->getState($adminId);
        if (!isset($state['cart'][$key]) || !is_array($state['cart'][$key])) {
            return false;
        }
        $state['cart'][$key]['quantity'] = max(1, $quantity);
        $this->saveState($adminId, $state);
        return true;
    }

    public function removeItem(int $adminId, int $key): bool
    {
        $state = $this->getState($adminId);
        if (!isset($state['cart'][$key])) {
            return false;
        }
        unset($state['cart'][$key]);
        $this->saveState($adminId, $state);
        return true;
    }

    public function updateTax(int $adminId, float $tax): void
    {
        $state = $this->getState($adminId);
        $state['cart']['tax'] = $tax;
        $this->saveState($adminId, $state);
    }

    public function updateDiscount(int $adminId, string $type, float $discount): array
    {
        $state = $this->getState($adminId);
        $items = array_filter($state['cart'], 'is_array');
        if (count($items) < 1) {
            return ['error' => true, 'message' => translate('cart_empty_warning')];
        }
        if ($type == 'percent' && ($discount < 0 || $discount > 100)) {
            return ['error' => true, 'message' => translate('Extra_discount_can_not_be_more_than_100_percent')];
        }
        $state['cart']['extra_discount_type'] = $type;
        $state['cart']['extra_discount'] = $discount;
        $this->saveState($adminId, $state);
        return ['error' => false];
    }

    public function variantPrice(int $adminId, array $requestData): array
    {
        $product = Product::with('modifierTemplates.items')->findOrFail($requestData['id']);
        $branchId = $this->branchId($adminId);
        $price = $product->price;
        $addonPrice = 0;
        $quantity = (int) ($requestData['quantity'] ?? 1);
        $allowedAddOnIds = $product->resolvedAddonIds();

        if (!empty($requestData['addon_id'])) {
            foreach ($requestData['addon_id'] as $addonId) {
                if (!in_array((int) $addonId, $allowedAddOnIds, true)) {
                    continue;
                }
                $addonPrice += ($requestData['addon-price' . $addonId] ?? 0) * ($requestData['addon-quantity' . $addonId] ?? 1);
            }
        }

        $branchProduct = ProductByBranch::where(['product_id' => $requestData['id'], 'branch_id' => $branchId])->first();
        if ($branchProduct) {
            $discountData = [
                'discount_type' => $branchProduct['discount_type'],
                'discount' => $branchProduct['discount'],
            ];
            if (!empty($requestData['variations']) && count($branchProduct->variations)) {
                $priceTotal = $branchProduct['price'] + Helpers::new_variation_price($branchProduct->variations, $requestData['variations']);
                $price = $priceTotal - Helpers::discount_calculate($discountData, $priceTotal);
            } else {
                $price = $branchProduct['price'] - Helpers::discount_calculate($discountData, $branchProduct['price']);
            }
        }

        return ['price' => Helpers::set_symbol(($price * $quantity) + $addonPrice)];
    }

    public function buildCartResponse(int $adminId): array
    {
        $state = $this->getState($adminId);
        $session = $state['session'];
        $cart = $state['cart'];

        $subtotal = 0;
        $addonPrice = 0;
        $discountOnProduct = 0;
        $addonTotalTax = 0;
        $totalTax = 0;
        $items = [];

        foreach ($cart as $key => $cartItem) {
            if (!is_array($cartItem)) {
                continue;
            }
            $productSubtotal = $cartItem['price'] * $cartItem['quantity'];
            $discountOnProduct += ($cartItem['discount'] * $cartItem['quantity']);
            $subtotal += $productSubtotal;
            $addonPrice += $cartItem['addon_price'] ?? 0;
            $addonTotalTax += $cartItem['addon_total_tax'] ?? 0;
            $product = Product::find($cartItem['id']);
            if ($product) {
                $totalTax += Helpers::new_tax_calculate($product, $cartItem['price'], $cartItem['discount_data'] ?? []) * $cartItem['quantity'];
            }
            $items[] = array_merge($cartItem, [
                'key' => $key,
                'line_total' => $productSubtotal,
                'image_url' => asset('storage/app/public/product/' . $cartItem['image']),
            ]);
        }

        $total = $subtotal + $addonPrice;
        $extraDiscount = $cart['extra_discount'] ?? 0;
        $extraDiscountType = $cart['extra_discount_type'] ?? 'amount';
        $extraDiscountAmount = ($extraDiscountType == 'percent' && $extraDiscount > 0)
            ? (($total - $discountOnProduct) * $extraDiscount / 100)
            : $extraDiscount;

        $taxPercent = $cart['tax'] ?? 0;
        if ($taxPercent > 0) {
            $totalTax = (($total - $discountOnProduct - $extraDiscountAmount) * $taxPercent) / 100;
        }

        $deliveryCharge = 0;
        if (($session['order_type'] ?? '') == 'home_delivery') {
            $distance = $session['address']['distance'] ?? 0;
            $areaId = $session['address']['area_id'] ?? null;
            $deliveryCharge = Helpers::get_delivery_charge(
                branchId: $session['branch_id'] ?? 1,
                distance: $distance,
                selectedDeliveryArea: $areaId,
                orderAmount: $total - $discountOnProduct - $extraDiscountAmount + $totalTax + $addonTotalTax
            );
        }

        $grandTotal = $total - $discountOnProduct - $extraDiscountAmount + $totalTax + $addonTotalTax + $deliveryCharge;

        return [
            'items' => $items,
            'session' => $session,
            'summary' => [
                'addon' => round($addonPrice, 2),
                'subtotal' => round($subtotal + $addonPrice, 2),
                'product_discount' => round($discountOnProduct, 2),
                'extra_discount' => round($extraDiscountAmount, 2),
                'extra_discount_type' => $extraDiscountType,
                'extra_discount_value' => $extraDiscount,
                'tax' => round($totalTax + $addonTotalTax, 2),
                'tax_percent' => $taxPercent,
                'delivery_charge' => round($deliveryCharge, 2),
                'total' => round($grandTotal, 2),
            ],
        ];
    }

    public function getRawCart(int $adminId): array
    {
        return $this->getState($adminId)['cart'];
    }

    public function clearAfterOrder(int $adminId): void
    {
        $this->saveState($adminId, $this->defaultState());
    }
}
