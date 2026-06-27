<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionItemGroup extends Model
{
    protected $fillable = [
        'promotion_id',
        'group_number',
        'label',
    ];

    protected $casts = [
        'promotion_id' => 'integer',
        'group_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function groupProducts(): HasMany
    {
        return $this->hasMany(PromotionGroupProduct::class);
    }

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'promotion_group_products',
            'promotion_item_group_id',
            'product_id'
        );
    }
}
