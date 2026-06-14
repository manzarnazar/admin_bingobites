<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Model\Order;
use Carbon\Carbon;
use function App\CentralLogics\translate;

class OrderStatusService
{
    public function resolveReadyAt(Order $order): ?Carbon
    {
        $preparationTime = (int) ($order->preparation_time ?? 0);

        if ($preparationTime > 0 && $order->updated_at) {
            return Carbon::parse($order->updated_at)->addMinutes($preparationTime);
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
        $order->order_status = 'done';

        if (!$order->save()) {
            return false;
        }

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
