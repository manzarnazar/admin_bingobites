<?php

namespace App\Services\Pos;

use App\CentralLogics\Helpers;
use App\Model\AddOn;
use App\Model\Branch;
use App\Model\CustomerAddress;
use App\Model\Notification;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\Product;
use App\Model\ProductByBranch;
use App\Model\Table;
use App\Models\OrderChangeAmount;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PosOrderService
{
    public function __construct(
        private PosCartService $cartService,
        private Order $order,
        private OrderDetail $orderDetail,
        private Product $product,
        private ProductByBranch $productByBranch,
        private Notification $notification,
        private User $user,
    ) {
    }

    public function placeOrder(int $adminId, array $requestData): array
    {
        $state = $this->cartService->getState($adminId);
        $cart = $state['cart'];
        $session = $state['session'];
        $items = array_filter($cart, 'is_array');

        if (count($items) < 1) {
            return ['error' => true, 'message' => translate('cart_empty_warning')];
        }

        $orderType = $session['order_type'] ?? 'take_away';

        if ($orderType == 'dine_in') {
            if (empty($session['table_id'])) {
                return ['error' => true, 'message' => translate('please select a table number')];
            }
            if (empty($session['people_number'])) {
                return ['error' => true, 'message' => translate('please enter people number')];
            }
            $table = Table::find($session['table_id']);
            if ($table && ($session['people_number'] > $table->capacity || $session['people_number'] < 1)) {
                return ['error' => true, 'message' => translate('enter valid people number between 1 to ' . $table->capacity)];
            }
        }

        $deliveryCharge = 0;
        $distance = 0;
        $areaId = null;
        $customerAddress = null;

        if ($orderType == 'home_delivery') {
            if (empty($session['customer_id'])) {
                return ['error' => true, 'message' => translate('please select a customer')];
            }
            if (empty($session['address'])) {
                return ['error' => true, 'message' => translate('please select a delivery address')];
            }
            $addressData = $session['address'];
            $distance = $addressData['distance'] ?? 0;
            $areaId = $addressData['area_id'] ?? null;
            $customerAddress = CustomerAddress::create([
                'address_type' => 'Home',
                'contact_person_name' => $addressData['contact_person_name'],
                'contact_person_number' => $addressData['contact_person_number'],
                'address' => $addressData['address'],
                'floor' => $addressData['floor'] ?? null,
                'road' => $addressData['road'] ?? null,
                'house' => $addressData['house'] ?? null,
                'longitude' => (string) ($addressData['longitude'] ?? ''),
                'latitude' => (string) ($addressData['latitude'] ?? ''),
                'user_id' => $session['customer_id'],
                'is_guest' => 0,
            ]);
        }

        $totalTaxAmount = 0;
        $totalAddonPrice = 0;
        $totalAddonTax = 0;
        $productPrice = 0;
        $orderDetails = [];
        $totalPriceForDiscountValidation = 0;

        $orderId = 100000 + $this->order->all()->count() + 1;
        if ($this->order->find($orderId)) {
            $orderId = $this->order->orderBy('id', 'DESC')->first()->id + 1;
        }

        foreach ($items as $c) {
            $discountOnProduct = ($c['discount'] * $c['quantity']);
            $productSubtotal = ($c['price']) * $c['quantity'];
            $totalPriceForDiscountValidation += $c['price'];

            $product = $this->product->find($c['id']);
            if (!$product) {
                continue;
            }

            $price = $c['price'];
            $product = Helpers::product_data_formatting($product);
            $addonData = Helpers::calculate_addon_price(AddOn::whereIn('id', $c['add_ons'] ?? [])->get(), $c['add_on_qtys'] ?? []);

            $addOnQtys = $c['add_on_qtys'] ?? [];
            array_walk($addOnQtys, function (&$qty) {
                $qty = (int) $qty;
            });

            $branchProduct = $this->productByBranch->where([
                'product_id' => $c['id'],
                'branch_id' => $session['branch_id'],
            ])->first();

            if (!$branchProduct) {
                return ['error' => true, 'message' => translate('Product not available in this branch')];
            }

            if (in_array($branchProduct->stock_type, ['daily', 'fixed'], true)) {
                $availableStock = $branchProduct->stock - $branchProduct->sold_quantity;
                if ($availableStock < $c['quantity']) {
                    return ['error' => true, 'message' => translate('stock limit exceeded')];
                }
            }

            $variationData = Helpers::get_varient($branchProduct->variations, $c['variations'] ?? []);
            $discountData = [
                'discount_type' => $branchProduct['discount_type'],
                'discount' => $branchProduct['discount'],
            ];
            $discount = Helpers::discount_calculate($discountData, $price);
            $variations = $variationData['variations'];

            $orD = [
                'product_id' => $c['id'],
                'product_details' => $product,
                'quantity' => $c['quantity'],
                'price' => $price,
                'tax_amount' => Helpers::new_tax_calculate($product, $price, $discountData),
                'discount_on_product' => $discount,
                'discount_type' => 'discount_on_product',
                'variation' => json_encode($variations),
                'add_on_ids' => json_encode($addonData['addons']),
                'add_on_qtys' => json_encode($c['add_on_qtys'] ?? []),
                'add_on_prices' => json_encode($c['add_on_prices'] ?? []),
                'add_on_taxes' => json_encode($c['add_on_tax'] ?? []),
                'add_on_tax_amount' => $c['addon_total_tax'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $totalTaxAmount += $orD['tax_amount'] * $c['quantity'];
            $totalAddonPrice += $addonData['total_add_on_price'];
            $totalAddonTax += $c['addon_total_tax'] ?? 0;
            $productPrice += $productSubtotal - $discountOnProduct;
            $orderDetails[] = $orD;

            if (in_array($branchProduct->stock_type, ['daily', 'fixed'], true)) {
                $branchProduct->sold_quantity += $c['quantity'];
                $branchProduct->save();
            }
        }

        $totalPrice = $productPrice + $totalAddonPrice;
        $extraDiscount = 0;
        if (isset($cart['extra_discount'])) {
            $extraDiscount = ($cart['extra_discount_type'] ?? 'amount') == 'percent' && $cart['extra_discount'] > 0
                ? (($totalPrice * $cart['extra_discount']) / 100)
                : $cart['extra_discount'];
            $totalPrice -= $extraDiscount;
        }

        if (isset($cart['extra_discount']) && ($cart['extra_discount_type'] ?? 'amount') == 'amount') {
            if ($cart['extra_discount'] > $totalPriceForDiscountValidation) {
                return ['error' => true, 'message' => translate('Extra_discount_can_not_be_more_total_product_price')];
            }
        }

        $tax = $cart['tax'] ?? 0;
        if ($tax > 0) {
            $totalTaxAmount = (($totalPrice * $tax) / 100);
        }

        if ($orderType == 'home_delivery') {
            $deliveryCharge = Helpers::get_delivery_charge(
                branchId: $session['branch_id'] ?? 1,
                distance: $distance,
                selectedDeliveryArea: $areaId,
                orderAmount: $totalPrice + $totalTaxAmount + $totalAddonTax
            );
        }

        if (empty($session['branch_id'])) {
            return ['error' => true, 'message' => translate('Branch select is required')];
        }

        $paymentType = $requestData['type'] ?? 'cash';

        try {
            DB::beginTransaction();

            $order = new Order();
            $order->id = $orderId;
            $order->user_id = $session['customer_id'] ?? null;
            $order->coupon_discount_title = null;
            $order->payment_status = ($orderType == 'take_away') ? 'paid' : (($orderType == 'dine_in' && $paymentType != 'pay_after_eating') ? 'paid' : 'unpaid');
            $order->order_status = $orderType == 'take_away' ? 'delivered' : 'confirmed';
            $order->order_type = ($orderType == 'take_away') ? 'pos' : (($orderType == 'dine_in') ? 'dine_in' : 'delivery');
            $order->coupon_code = $requestData['coupon_code'] ?? null;
            $order->payment_method = $paymentType;
            $order->transaction_reference = $requestData['transaction_reference'] ?? null;
            $order->delivery_address_id = $orderType == 'home_delivery' ? $customerAddress->id : null;
            $order->delivery_date = Carbon::now()->format('Y-m-d');
            $order->delivery_time = Carbon::now()->format('H:i:s');
            $order->order_note = $requestData['order_note'] ?? null;
            $order->checked = 1;
            $order->extra_discount = $extraDiscount;
            $order->total_tax_amount = $totalTaxAmount;
            $order->order_amount = $totalPrice + $totalTaxAmount + $totalAddonTax + $deliveryCharge;
            $order->delivery_charge = $deliveryCharge;
            $order->coupon_discount_amount = 0.00;
            $order->branch_id = $session['branch_id'];
            $order->table_id = $session['table_id'] ?? null;
            $order->number_of_people = $session['people_number'] ?? null;
            $order->created_at = now();
            $order->updated_at = now();
            $order->save();

            foreach ($orderDetails as $key => $item) {
                $orderDetails[$key]['order_id'] = $order->id;
            }
            $this->orderDetail->insert($orderDetails);

            if (in_array($paymentType, ['cash', 'card'], true)) {
                $orderChangeAmount = new OrderChangeAmount();
                $orderChangeAmount->order_id = $order->id;
                $orderChangeAmount->order_amount = $order->order_amount;
                $orderChangeAmount->paid_amount = $requestData['paid_amount'] ?? $order->order_amount;
                $orderChangeAmount->save();
            }

            DB::commit();

            $this->cartService->clearAfterOrder($adminId);

            if ($order->order_type == 'dine_in') {
                $notification = $this->notification;
                $notification->title = 'You have a new order from POS - (Order Confirmed). ';
                $notification->description = $order->id;
                $notification->status = 1;
                $notification->order_id = $order->id;
                $notification->order_status = $order->order_status;
                try {
                    Helpers::send_push_notif_to_topic(
                        data: $notification,
                        topic: "kitchen-{$order->branch_id}",
                        type: 'general',
                        isNotificationPayloadRemove: true
                    );
                } catch (\Exception $e) {
                    // ignore push failures
                }
            }

            if ($order->order_type == 'delivery') {
                $customer = $this->user->find($order->user_id);
                $customerFcmToken = $customer?->cm_firebase_token;
                if ($customerFcmToken) {
                    try {
                        Helpers::send_push_notif_to_device($customerFcmToken, [
                            'title' => translate('Order'),
                            'description' => translate('Order confirmed'),
                            'order_id' => $order->id,
                            'image' => '',
                            'type' => 'order_status',
                        ]);
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
                try {
                    $emailServices = Helpers::get_business_settings('mail_config');
                    $orderMailStatus = Helpers::get_business_settings('place_order_mail_status_user');
                    if (isset($emailServices['status']) && $emailServices['status'] == 1 && $orderMailStatus == 1 && $customer) {
                        Mail::to($customer->email)->send(new \App\Mail\OrderPlaced($order->id));
                    }
                } catch (\Exception $e) {
                    // ignore
                }
            }

            return [
                'error' => false,
                'order_id' => $order->id,
                'message' => translate('order_placed_successfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            info($e);
            return ['error' => true, 'message' => translate('failed_to_place_order')];
        }
    }
}
