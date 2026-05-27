<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'selection_type',
        'min',
        'max',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'min' => 'integer',
        'max' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('position');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'modifier_group_product')
            ->withPivot(['selection_type', 'min', 'max', 'is_required', 'position', 'is_default_enabled'])
            ->withTimestamps()
            ->orderBy('modifier_group_product.position');
    }
}
