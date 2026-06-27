<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionGroupProduct extends Model
{
    protected $fillable = [
        'promotion_item_group_id',
        'product_id',
    ];

    protected $casts = [
        'promotion_item_group_id' => 'integer',
        'product_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(PromotionItemGroup::class, 'promotion_item_group_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
