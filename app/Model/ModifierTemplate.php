<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'selection_type',
        'min_select',
        'max_select',
        'is_required',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'min_select' => 'integer',
        'max_select' => 'integer',
        'is_required' => 'integer',
        'is_active' => 'integer',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ModifierTemplateItem::class)->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_modifier_template')
            ->withPivot(['sort_order', 'is_active'])
            ->withTimestamps();
    }
}
