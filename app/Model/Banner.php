<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    public const PROMOTION_TYPE_BOGO = 'bogo';
    public const PROMOTION_TYPE_PERCENT_OFF = 'percent_off';
    public const PROMOTION_TYPE_FIXED_AMOUNT = 'fixed_amount';

    protected $casts = [
        'reward_discount_value' => 'float',
        'discount_cheapest_percent' => 'float',
        'discount_expensive_percent' => 'float',
        'charge_paid_addons' => 'boolean',
        'charge_reward_addons' => 'boolean',
        'order_types' => 'array',
        'payment_methods' => 'array',
        'once_per_customer' => 'boolean',
        'max_reward_qty' => 'integer',
        'usage_per_customer' => 'integer',
        'total_usage_limit' => 'integer',
        'usage_count' => 'integer',
        'status' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }

    public function scopeCurrentlyValid($query)
    {
        if (!Schema::hasColumn('banners', 'start_date')) {
            return $query;
        }

        $now = Carbon::now();

        return $query
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('total_usage_limit')
                    ->orWhereColumn('usage_count', '<', 'total_usage_limit');
            });
    }

    public function groupItems(): HasMany
    {
        return $this->hasMany(BannerGroupItem::class)->orderBy('sort_order');
    }

    public function groupOneItems(): HasMany
    {
        return $this->groupItems()->where('group_number', 1);
    }

    public function groupTwoItems(): HasMany
    {
        return $this->groupItems()->where('group_number', 2);
    }

    public function promotionUsages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    public function getImageFullPathAttribute(): string
    {
        $image = $this->image ?? null;
        $path = asset('public/assets/admin/img/icons/upload_img2.png');

        if (!is_null($image) && Storage::disk('public')->exists('banner/' . $image)) {
            $path = asset('storage/app/public/banner/' . $image);
        }
        return $path;
    }

    public function isOrderTypeAllowed(string $orderType): bool
    {
        if ($this->order_type_mode === 'any' || empty($this->order_types)) {
            return true;
        }

        return in_array($orderType, $this->order_types ?? [], true);
    }

    public function isPaymentMethodAllowed(?string $paymentMethod): bool
    {
        if (empty($this->payment_methods)) {
            return true;
        }

        return $paymentMethod !== null && in_array($paymentMethod, $this->payment_methods, true);
    }
}
