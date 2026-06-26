<?php

namespace App\Model;

use App\CentralLogics\Helpers;
use App\Models\CuisineProduct;
use App\Models\Cuisine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

class Product extends Model
{
    protected $casts = [
        'tax' => 'float',
        'price' => 'float',
        'status' => 'integer',
        'discount' => 'float',
        'set_menu' => 'integer',
        'popularity_count' => 'integer',
        'is_recommended' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getPriceAttribute($price): float
    {
        return (float)Helpers::set_price($price);
    }

    public function getDiscountAttribute($discount): float
    {
        return (float)Helpers::set_price($discount);
    }

    public function translations(): MorphMany
    {
        return $this->morphMany('App\Model\Translation', 'translationable');
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }

    public function scopeVisible($query)
    {
        return $query->where('visibility', '=', 1);
    }

    public function scopeProductType($query, $type)
    {
        if ($type == 'veg') {
            return $query->where('product_type', 'veg');
        } elseif ($type == 'non_veg') {
            return $query->where('product_type', 'non_veg');
        }
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function rating(): HasMany
    {
        return $this->hasMany(Review::class)
            ->select(DB::raw('avg(rating) average, product_id'))
            ->groupBy('product_id');
    }

    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class)->latest();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function modifierTemplates(): BelongsToMany
    {
        return $this->belongsToMany(ModifierTemplate::class, 'product_modifier_template')
            ->withPivot(['sort_order', 'is_active'])
            ->withTimestamps();
    }

    public function product_by_branch(): HasMany
    {
        return $this->hasMany(ProductByBranch::class)->where(['branch_id' => auth('branch')->id()]);
    }

    public function branch_product(): HasOne
    {
        return $this->hasOne(ProductByBranch::class)->where(['branch_id' => Config::get('branch_id')]);
    }

    public function scopeBranchProductAvailability($query)
    {
        return $query->whereHas('branch_product', function ($q) {
            $q->where('is_available', 1);
        });
    }

    public function branch_products(): HasMany
    {
        return $this->hasMany(ProductByBranch::class)->where(['branch_id' => session()->get('branch_id') ?? 1]);
    }

    public function main_branch_product(): HasOne
    {
        return $this->hasOne(ProductByBranch::class)->where(['branch_id' => 1]);
    }
    public function sub_branch_product(): HasOne
    {
        return $this->hasOne(ProductByBranch::class)->where(['branch_id' => auth('branch')->id()]);
    }

    public function cuisines(): BelongsToMany
    {
        return $this->belongsToMany(Cuisine::class, 'cuisine_product', 'product_id', 'cuisine_id');
    }

    public function b_product()
    {
        return $this->hasMany(ProductByBranch::class);
    }

    public function getImageFullPathAttribute(): string
    {
        $image = $this->image ?? null;
        $path = asset('public/assets/admin/img/160x160/img2.jpg');

        if (!is_null($image) && Storage::disk('public')->exists('product/' . $image)) {
            $path = asset('storage/app/public/product/' . $image);
        }
        return $path;
    }

    public function getCategoryAttribute()
    {
        $categories = json_decode($this->category_ids, true);

        $categoryWithPositionOne = collect($categories)->firstWhere('position', 1);

        if ($categoryWithPositionOne) {
            $category = Category::find($categoryWithPositionOne['id']);
            if ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                ];
            }
        }

        return null;
    }

    public function getSubCategoryAttribute()
    {
        $categories = json_decode($this->category_ids, true);

        $categoryWithPositionOne = collect($categories)->firstWhere('position', 2);

        if ($categoryWithPositionOne) {
            $category = Category::find($categoryWithPositionOne['id']);
            if ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                ];
            }
        }

        return null;
    }

    public function legacyAddonIds(): array
    {
        $legacyIds = json_decode($this->add_ons ?? '[]', true) ?: [];
        return array_values(array_unique(array_map('intval', $legacyIds)));
    }

    public function resolvedAddonIds(): array
    {
        $templateAddonIds = [];

        if ($this->relationLoaded('modifierTemplates')) {
            foreach ($this->modifierTemplates as $template) {
                if (($template->is_active ?? 1) && ($template->pivot->is_active ?? 1) && $template->relationLoaded('items')) {
                    foreach ($template->items as $item) {
                        if (($item->is_active ?? 1) && !empty($item->add_on_id)) {
                            $templateAddonIds[] = (int) $item->add_on_id;
                        }
                    }
                }
            }
        } else {
            $templateAddonIds = $this->modifierTemplates()
                ->wherePivot('is_active', 1)
                ->where('modifier_templates.is_active', 1)
                ->join('modifier_template_items', 'modifier_template_items.modifier_template_id', '=', 'modifier_templates.id')
                ->where('modifier_template_items.is_active', 1)
                ->pluck('modifier_template_items.add_on_id')
                ->map(fn($id) => (int) $id)
                ->toArray();
        }

        if (count($templateAddonIds) > 0) {
            return array_values(array_unique($templateAddonIds));
        }

        return $this->legacyAddonIds();
    }

    /**
     * Expose attached modifier templates as product variations for customer app UI.
     */
    public function modifierTemplatesAsVariations(): array
    {
        $templates = $this->relationLoaded('modifierTemplates')
            ? $this->modifierTemplates
            : $this->modifierTemplates()
                ->where('modifier_templates.is_active', 1)
                ->wherePivot('is_active', 1)
                ->orderBy('product_modifier_template.sort_order')
                ->with(['items' => function ($query) {
                    $query->where('is_active', 1)->orderBy('sort_order');
                }, 'items.addon'])
                ->get();

        $variations = [];
        foreach ($templates as $template) {
            if (!($template->is_active ?? 1) || !($template->pivot->is_active ?? 1)) {
                continue;
            }

            $values = [];
            $items = $template->relationLoaded('items') ? $template->items : $template->items()->where('is_active', 1)->orderBy('sort_order')->with('addon')->get();
            foreach ($items as $item) {
                if (!($item->is_active ?? 1) || !$item->addon) {
                    continue;
                }
                $optionMaxQty = $item->max_qty ?? $item->addon->max_qty ?? null;
                $value = [
                    'label' => $item->addon->name,
                    'optionPrice' => (float) $item->addon->price,
                    'addon_id' => (int) $item->add_on_id,
                ];
                if ($optionMaxQty !== null && (int) $optionMaxQty > 0) {
                    $value['optionMaxQty'] = (int) $optionMaxQty;
                }
                $values[] = $value;
            }

            if (count($values) === 0) {
                continue;
            }

            $maxSelect = (int) $template->max_select;
            if ($template->selection_type === 'single') {
                $maxSelect = 1;
            }

            $variations[] = [
                'name' => $template->name,
                'type' => $template->selection_type,
                'min' => (int) $template->min_select,
                'max' => $maxSelect,
                'required' => ($template->is_required ?? 0) ? 'on' : 'off',
                'values' => $values,
            ];
        }

        return $variations;
    }

    public function resolvedAddons(): Collection
    {
        $addonIds = $this->resolvedAddonIds();
        if (empty($addonIds)) {
            return collect();
        }
        return AddOn::whereIn('id', $addonIds)->get();
    }

    protected static function booted(): void
    {
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                return $query->where('locale', app()->getLocale());
            }]);
        });
    }

}
