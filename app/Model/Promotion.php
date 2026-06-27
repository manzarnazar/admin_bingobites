<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Promotion extends Model
{
    protected $fillable = [
        'title',
        'headline',
        'description',
        'image',
        'type',
        'discount_cheapest_percent',
        'discount_expensive_percent',
        'charge_modifier_addons',
        'customer_type',
        'order_type',
        'once_per_customer',
        'is_exclusive',
        'start_date',
        'end_date',
        'highlight_group',
        'status',
    ];

    protected $casts = [
        'discount_cheapest_percent' => 'integer',
        'discount_expensive_percent' => 'integer',
        'charge_modifier_addons' => 'boolean',
        'once_per_customer' => 'boolean',
        'is_exclusive' => 'boolean',
        'highlight_group' => 'integer',
        'status' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    public function itemGroups(): HasMany
    {
        return $this->hasMany(PromotionItemGroup::class)->orderBy('group_number');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function getImageFullPathAttribute(): string
    {
        $image = $this->image ?? null;
        $path = asset('public/assets/admin/img/icons/upload_img2.png');

        if (!is_null($image) && Storage::disk('public')->exists('promotion/' . $image)) {
            $path = asset('storage/app/public/promotion/' . $image);
        }

        return $path;
    }
}
