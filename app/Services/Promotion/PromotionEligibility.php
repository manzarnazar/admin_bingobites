<?php

namespace App\Services\Promotion;

use App\Model\Order;
use App\Model\Promotion;
use App\Model\PromotionRedemption;
use Carbon\Carbon;

class PromotionEligibility
{
    public function check(
        Promotion $promotion,
        int $userId,
        string $orderType = 'any',
        bool $requireAuth = true
    ): array {
        if ($requireAuth && $userId <= 0) {
            return [
                'eligible' => false,
                'reason' => translate('Please login to use this promotion'),
            ];
        }

        if (!$promotion->status) {
            return [
                'eligible' => false,
                'reason' => translate('This promotion is not active'),
            ];
        }

        $today = Carbon::now()->toDateString();
        if ($promotion->start_date && $promotion->start_date->toDateString() > $today) {
            return [
                'eligible' => false,
                'reason' => translate('This promotion has not started yet'),
            ];
        }

        if ($promotion->end_date && $promotion->end_date->toDateString() < $today) {
            return [
                'eligible' => false,
                'reason' => translate('This promotion has expired'),
            ];
        }

        if ($promotion->order_type !== 'any') {
            $normalizedOrderType = $orderType === 'take_away' ? 'take_away' : ($orderType === 'delivery' ? 'delivery' : $orderType);
            if ($promotion->order_type !== $normalizedOrderType) {
                return [
                    'eligible' => false,
                    'reason' => translate('This promotion is not available for your order type'),
                ];
            }
        }

        $orderCount = Order::where(['user_id' => $userId, 'is_guest' => 0])->count();

        if ($promotion->customer_type === 'new' && $orderCount > 0) {
            return [
                'eligible' => false,
                'reason' => translate('This promotion is only for new customers'),
            ];
        }

        if ($promotion->customer_type === 'returning' && $orderCount === 0) {
            return [
                'eligible' => false,
                'reason' => translate('This promotion is only for returning customers'),
            ];
        }

        if ($promotion->once_per_customer) {
            $alreadyRedeemed = PromotionRedemption::where([
                'promotion_id' => $promotion->id,
                'user_id' => $userId,
            ])->exists();

            if ($alreadyRedeemed) {
                return [
                    'eligible' => false,
                    'reason' => translate('You have already used this promotion'),
                ];
            }
        }

        return [
            'eligible' => true,
            'reason' => null,
        ];
    }
}
