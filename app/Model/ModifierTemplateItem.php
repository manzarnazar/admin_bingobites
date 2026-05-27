<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierTemplateItem extends Model
{
    protected $fillable = [
        'modifier_template_id',
        'add_on_id',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'modifier_template_id' => 'integer',
        'add_on_id' => 'integer',
        'sort_order' => 'integer',
        'is_default' => 'integer',
        'is_active' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function modifierTemplate(): BelongsTo
    {
        return $this->belongsTo(ModifierTemplate::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(AddOn::class, 'add_on_id');
    }
}
