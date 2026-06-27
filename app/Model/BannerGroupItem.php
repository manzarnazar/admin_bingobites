<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BannerGroupItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'banner_id' => 'integer',
        'group_number' => 'integer',
        'product_id' => 'integer',
        'variations' => 'array',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Banner::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
