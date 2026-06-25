<?php

namespace App\Services;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Model\Order;
use App\Model\PointTransitions;
use App\Models\OrderPartialPayment;
use App\Models\ReferralCustomer;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use function App\CentralLogics\translate;

class OrderStatusService
{
    public function resolveReadyAt(Order $order): ?Carbon
    {
        $preparationTime = (int) ($order->preparation_time ?? 0);

        if ($preparationTime > 0) {
            if ($order->cooking_started_at) {
                return Carbon::parse($order->cooking_started_at)->addMinutes($preparationTime);
            }

            if ($order->updated_at) {
                return Carbon::parse($order->updated_at)->addMinutes($preparationTime);
            }
        }

        if ($order->delivery_date && $order->delivery_time) {
            $dateTimeString = $order->delivery_date . ' ' . $order->delivery_time;

            try {
                return Carbon::createFromFormat('Y-m-d H:i:s', $dateTimeString);
            } catch (\Exception $e) {
                try {
                    return Carbon::createFromFormat('Y-m-d H:i', substr($dateTimeString, 0, 16));
                } catch (\Exception $e2) {
                    return null;
                }
            }
        }

        return null;
    }

    public function markOrderDone(Order $order, bool $notifyKitchen = false): bool
    {
        if ($order->order_status !== 'cooking') {
            return false;
        }

        $order->loadMissing(['customer', 'delivery_man', 'guest']);

        $updated = Order::query()
            ->where('id', $order->id)
            ->where('order_status', 'cooking')
            ->update(['order_status' => 'done']);

        if (!$updated) {
            return false;
        }

        $order->order_status = 'done';

        $this->notifyDeliveryman($order);
        $this->notifyOrderCustomer($order, 'done');

        if ($notifyKitchen) {
            $this->notifyKitchenCookingDone($order);
        }

        return true;
    }

    public function notifyOrderCustomerForStatus(Order $order, string $status): void
    {
        $this->notifyOrderCustomer($order, $status);
    }

    /**
     * @return array{success: bool, order?: Order, code?: string, message?: string}
     */
    public function finalizeTakeawayFromCooking(Order $order): array
    {
        if (!in_array($order->order_type, ['take_away', 'pos'], true)) {
            return [
                'success' => false,
                'code' => 'order_type',
                'message' => translate('This order type cannot be completed from kitchen'),
            ];
        }

        if ($order->order_status === 'delivered') {
            return [
                'success' => true,
                'order' => $order,
            ];
        }

        if ($order->order_status !== 'cooking') {
            return [
                'success' => false,
                'code' => 'order_status',
                'message' => translate('Invalid status transition'),
            ];
        }

        if ($order->transaction_reference == null
            && !in_array($order->payment_method, ['cash_on_delivery', 'wallet_payment', 'offline_payment'], true)
        ) {
            return [
                'success' => false,
                'code' => 'payment',
                'message' => translate('add_your_payment_reference_first'),
            ];
        }

        $order->loadMissing(['customer', 'delivery_man', 'guest', 'transaction']);

        try {
            DB::transaction(function () use ($order) {
                $updated = Order::query()
                    ->where('id', $order->id)
                    ->where('order_status', 'cooking')
                    ->update([
                        'order_status' => 'delivered',
                        'payment_status' => 'paid',
                    ]);

                if (!$updated) {
                    throw new \RuntimeException('Status did not change');
                }

                $order->order_status = 'delivered';
                $order->payment_status = 'paid';

                $this->applyDeliveredSideEffects($order);
            });
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'code' => 'order',
                'message' => translate('Status did not changed'),
            ];
        }

        $order->refresh();
        $this->notifyOrderCustomer($order, 'delivered');

        return [
            'success' => true,
            'order' => $order,
        ];
    }

    /**
     * @return array{success: bool, order?: Order, code?: string, message?: string}
     */
    public function completeOrderFromKitchen(Order $order): array
    {
        if ($order->order_status !== 'done') {
            return [
                'success' => false,
                'code' => 'order_status',
                'message' => translate('Invalid status transition'),
            ];
        }

        if (in_array($order->order_type, ['delivery', 'home_delivery'], true)) {
            return [
                'success' => false,
                'code' => 'order_type',
                'message' => translate('Delivery orders must be completed by the delivery man'),
            ];
        }

        $isTakeAway = in_array($order->order_type, ['take_away', 'pos'], true);
        $isDineIn = $order->order_type === 'dine_in';

        if (!$isTakeAway && !$isDineIn) {
            return [
                'success' => false,
                'code' => 'order_type',
                'message' => translate('This order type cannot be completed from kitchen'),
            ];
        }

        if ($isTakeAway
            && $order->transaction_reference == null
            && !in_array($order->payment_method, ['cash_on_delivery', 'wallet_payment', 'offline_payment'], true)
        ) {
            return [
                'success' => false,
                'code' => 'payment',
                'message' => translate('add_your_payment_reference_first'),
            ];
        }

        if ($isDineIn && $order->payment_status !== 'paid') {
            return [
                'success' => false,
                'code' => 'payment',
                'message' => translate('Please update payment status first!'),
            ];
        }

        $targetStatus = $isTakeAway ? 'delivered' : 'completed';

        $order->loadMissing(['customer', 'delivery_man', 'guest', 'transaction']);

        try {
            DB::transaction(function () use ($order, $isTakeAway, $targetStatus) {
                if ($isTakeAway) {
                    $this->applyDeliveredSideEffects($order);
                }

                $order->order_status = $targetStatus;
                if ($isTakeAway) {
                    $order->payment_status = 'paid';
                }
                $order->save();
            });
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'code' => 'order',
                'message' => translate('Status did not changed'),
            ];
        }

        $order->refresh();
        $this->notifyOrderCustomer($order, $targetStatus);

        return [
            'success' => true,
            'order' => $order,
        ];
    }

    private function applyDeliveredSideEffects(Order $order): void
    {
        if ($order->is_guest != 0) {
            return;
        }

        if ($order->user_id) {
            $loyaltyAlreadyAwarded = PointTransitions::query()
                ->where('reference', $order->id)
                ->where('type', 'order_place')
                ->exists();

            if (!$loyaltyAlreadyAwarded) {
                CustomerLogic::create_loyalty_point_transaction(
                    $order->user_id,
                    $order->id,
                    $order->order_amount,
                    'order_place'
                );
            }
        }

        if ($order->transaction == null) {
            OrderLogic::create_transaction($order, 'admin');
        }

        $user = User::query()->find($order->user_id);
        if (!$user) {
            return;
        }

        $referralData = $user->referral_customer_details;
        if ($referralData && $referralData->is_used_by_refer == 0) {
            $referralEarningAmount = $referralData->ref_by_earning_amount ?? 0;
            $referredByUser = User::query()->find($user->refer_by);

            if ($referralEarningAmount > 0 && $referredByUser) {
                CustomerLogic::referral_earning_wallet_transaction(
                    $order->user_id,
                    'referral_order_place',
                    $referredByUser->id,
                    $referralEarningAmount
                );
            }

            ReferralCustomer::where('user_id', $order->user_id)->update(['is_used_by_refer' => 1]);
        }

        if ($order->payment_method === 'cash_on_delivery') {
            $partialData = OrderPartialPayment::where(['order_id' => $order->id])->first();
            if ($partialData) {
                $partial = new OrderPartialPayment;
                $partial->order_id = $order->id;
                $partial->paid_with = 'cash_on_delivery';
                $partial->paid_amount = $partialData->due_amount;
                $partial->due_amount = 0;
                $partial->save();
            }
        }
    }

    private function notifyDeliveryman(Order $order): void
    {
        $deliverymanFcmToken = $order->delivery_man?->fcm_token;

        if (is_null($deliverymanFcmToken)) {
            return;
        }

        try {
            Helpers::send_push_notif_to_device($deliverymanFcmToken, [
                'title' => translate('Order'),
                'description' => translate('cooking done'),
                'order_id' => $order->id,
                'image' => '',
                'type' => '',
            ]);
        } catch (\Exception $e) {
            // ignore push failures
        }
    }

    private function notifyKitchenCookingDone(Order $order): void
    {
        $data = [
            'title' => translate('Order') . ' #' . $order->id . ' ' . translate('is ready'),
            'description' => (string) $order->id,
            'order_id' => $order->id,
            'order_status' => 'done',
            'image' => '',
            'is_confirmation' => '1',
        ];

        try {
            Helpers::send_push_notif_to_topic(
                data: $data,
                topic: "kitchen-{$order->branch_id}",
                type: 'cooking_done',
                isNotificationPayloadRemove: true
            );
        } catch (\Exception $e) {
            // ignore push failures
        }
    }

    private function notifyOrderCustomer(Order $order, string $status): void
    {
        $message = Helpers::order_status_update_message($status);
        if (!$message) {
            $message = $status === 'cooking'
                ? translate('Your order is being prepared')
                : translate('Your order is ready');
        }

        $local = $order->is_guest == 0
            ? ($order->customer?->language_code ?? 'en')
            : ($order->guest?->language_code ?? 'en');

        if ($local != 'en') {
            $statusKey = Helpers::order_status_message_key($status);
            $translatedMessage = \App\Model\BusinessSetting::with('translations')
                ->where(['key' => $statusKey])
                ->first();
            if (isset($translatedMessage?->translations)) {
                foreach ($translatedMessage->translations as $translation) {
                    if ($local == $translation->locale) {
                        $message = $translation->value;
                    }
                }
            }
        }

        $restaurantName = Helpers::get_business_settings('restaurant_name');
        $deliverymanName = $order->delivery_man
            ? $order->delivery_man->f_name . ' ' . $order->delivery_man->l_name
            : '';
        $customerName = $order->is_guest == 0
            ? ($order->customer ? $order->customer->f_name . ' ' . $order->customer->l_name : '')
            : ($order->guest ? $order->guest->f_name . ' ' . $order->guest->l_name : '');

        $value = Helpers::text_variable_data_format(
            value: $message,
            user_name: $customerName,
            restaurant_name: $restaurantName,
            delivery_man_name: $deliverymanName,
            order_id: $order->id
        );

        $customerFcmToken = null;
        if ($order->is_guest == 0) {
            $customerFcmToken = $order->customer?->cm_firebase_token;
        } elseif ($order->is_guest == 1) {
            $customerFcmToken = $order->guest?->fcm_token;
        }

        if (!$value || !$customerFcmToken) {
            return;
        }

        try {
            Helpers::send_push_notif_to_device($customerFcmToken, [
                'title' => translate('Order'),
                'description' => $value,
                'order_id' => $order->id,
                'image' => '',
                'type' => 'order_status',
                'order_status' => $order->order_status,
            ]);
        } catch (\Exception $e) {
            // ignore push failures
        }
    }
}
