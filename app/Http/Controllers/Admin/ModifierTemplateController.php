<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\AddOn;
use App\Model\ModifierTemplate;
use App\Model\Product;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModifierTemplateController extends Controller
{
    public function __construct(
        private ModifierTemplate $modifierTemplate,
        private AddOn $addOn,
        private Product $product
    ){}

    public function index(Request $request): Renderable
    {
        $queryParam = [];
        $search = $request['search'];
        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $templates = $this->modifierTemplate->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $request['search']];
        } else {
            $templates = $this->modifierTemplate;
        }

        $templates = $templates->with('items.addon')
            ->withCount('products')
            ->orderByDesc('id')
            ->paginate(Helpers::getPagination())
            ->appends($queryParam);

        $addons = $this->addOn->orderBy('name')->get();
        $products = $this->product->active()->orderBy('name')->get(['id', 'name']);

        return view('admin-views.modifier-template.index', compact('templates', 'addons', 'products', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|unique:modifier_templates,name',
            'selection_type' => 'required|in:single,multi',
            'min_select' => 'required|integer|min:0',
            'max_select' => 'required|integer|min:0',
            'items' => 'required|array|min:1',
            'items.*.sort_order' => 'nullable|integer|min:0',
            'items.*.max_qty' => 'nullable|integer|min:1',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $itemValidation = $this->validateTemplateItems($request);
        if ($itemValidation !== true) {
            Toastr::error($itemValidation);
            return back()->withInput();
        }

        try {
            $builtItems = $this->buildTemplateItems($request);
        } catch (ValidationException $e) {
            Toastr::error(collect($e->errors())->flatten()->first());
            return back()->withInput();
        }

        $validated = $this->validateItemSelectionRules($builtItems, $request);
        if ($validated !== true) {
            Toastr::error($validated);
            return back()->withInput();
        }

        DB::transaction(function () use ($request, $builtItems) {
            $template = $this->modifierTemplate->create([
                'name' => $request->name,
                'description' => null,
                'selection_type' => $request->selection_type,
                'min_select' => (int) $request->min_select,
                'max_select' => (int) $request->max_select,
                'is_required' => $request->has('is_required') ? 1 : 0,
                'is_active' => $request->has('is_active') ? 1 : 0,
                'created_by' => auth('admin')->id(),
            ]);

            $template->items()->createMany($builtItems);

            $this->syncProducts($template, $request->product_ids ?? []);
        });

        Toastr::success(translate('Modifier template created successfully!'));
        return back();
    }

    public function edit($id): Renderable
    {
        $template = $this->modifierTemplate->with(['items', 'products'])->findOrFail($id);
        $addons = $this->addOn->orderBy('name')->get();
        $products = $this->product->active()->orderBy('name')->get(['id', 'name']);

        return view('admin-views.modifier-template.edit', compact('template', 'addons', 'products'));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'name' => 'required|unique:modifier_templates,name,' . $id,
            'selection_type' => 'required|in:single,multi',
            'min_select' => 'required|integer|min:0',
            'max_select' => 'required|integer|min:0',
            'items' => 'required|array|min:1',
            'items.*.sort_order' => 'nullable|integer|min:0',
            'items.*.max_qty' => 'nullable|integer|min:1',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $itemValidation = $this->validateTemplateItems($request);
        if ($itemValidation !== true) {
            Toastr::error($itemValidation);
            return back()->withInput();
        }

        try {
            $builtItems = $this->buildTemplateItems($request);
        } catch (ValidationException $e) {
            Toastr::error(collect($e->errors())->flatten()->first());
            return back()->withInput();
        }

        $validated = $this->validateItemSelectionRules($builtItems, $request);
        if ($validated !== true) {
            Toastr::error($validated);
            return back()->withInput();
        }

        DB::transaction(function () use ($request, $id, $builtItems) {
            $template = $this->modifierTemplate->findOrFail($id);
            $template->update([
                'name' => $request->name,
                'description' => null,
                'selection_type' => $request->selection_type,
                'min_select' => (int) $request->min_select,
                'max_select' => (int) $request->max_select,
                'is_required' => $request->has('is_required') ? 1 : 0,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);

            $template->items()->delete();
            $template->items()->createMany($builtItems);

            $this->syncProducts($template, $request->product_ids ?? []);
        });

        Toastr::success(translate('Modifier template updated successfully!'));
        return redirect()->route('admin.modifier-template.index');
    }

    public function delete(Request $request, $id): RedirectResponse
    {
        $template = $this->modifierTemplate->findOrFail($id);
        $template->items()->delete();
        $template->products()->detach();
        $template->delete();

        Toastr::success(translate('Modifier template removed!'));
        return back();
    }

    public function status($id, $status): RedirectResponse
    {
        $template = $this->modifierTemplate->findOrFail($id);
        $template->is_active = $status;
        $template->save();

        Toastr::success(translate('updated successfully!'));
        return back();
    }

    private function syncProducts(ModifierTemplate $template, array $productIds = []): void
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        $syncData = [];

        foreach ($productIds as $productId) {
            $product = $this->product->find($productId);
            if (!$product) {
                continue;
            }

            $existing = $product->modifierTemplates()
                ->where('modifier_templates.id', $template->id)
                ->first();

            if ($existing) {
                $sortOrder = (int) $existing->pivot->sort_order;
            } else {
                $maxSort = $product->modifierTemplates()->max('product_modifier_template.sort_order');
                $sortOrder = $maxSort !== null ? ((int) $maxSort) + 1 : 0;
            }

            $syncData[$productId] = [
                'sort_order' => $sortOrder,
                'is_active' => 1,
            ];
        }

        $template->products()->sync($syncData);
    }

    private function validateTemplateItems(Request $request): bool|string
    {
        foreach ($request->items ?? [] as $index => $item) {
            $addOnId = $item['add_on_id'] ?? null;
            $newName = trim((string) ($item['new_name'] ?? ''));
            $newPrice = $item['new_price'] ?? null;
            $hasExisting = !empty($addOnId) && $addOnId !== 'new';
            $hasNew = $addOnId === 'new' || (!$hasExisting && ($newName !== '' || ($newPrice !== null && $newPrice !== '')));

            if ($hasExisting && $newName !== '') {
                return translate('Each template item must use either an existing addon or a new addon, not both.');
            }

            if (!$hasExisting && !$hasNew) {
                return translate('Each template item must have an addon selected or new addon details.');
            }

            if ($hasExisting && !$this->addOn->where('id', $addOnId)->exists()) {
                return translate('Selected addon is invalid.');
            }

            if ($addOnId === 'new' || (!$hasExisting && $newName !== '')) {
                if ($newName === '') {
                    return translate('New addon name is required.');
                }
                if (mb_strlen($newName) > 255) {
                    return translate('New addon name is too long.');
                }
                if ($newPrice === null || $newPrice === '' || !is_numeric($newPrice) || (float) $newPrice < 0) {
                    return translate('New addon price must be zero or greater.');
                }
            }
        }

        return true;
    }

    private function buildTemplateItems(Request $request): array
    {
        $items = [];

        foreach ($request->items as $index => $item) {
            $items[] = [
                'add_on_id' => $this->resolveItemAddonId($item),
                'sort_order' => $item['sort_order'] ?? $index,
                'is_default' => isset($item['is_default']) ? 1 : 0,
                'is_active' => isset($item['is_active']) ? 1 : 0,
                'max_qty' => !empty($item['max_qty']) ? (int) $item['max_qty'] : null,
            ];
        }

        return $items;
    }

    private function resolveItemAddonId(array $item): int
    {
        $addOnId = $item['add_on_id'] ?? null;

        if (!empty($addOnId) && $addOnId !== 'new') {
            return (int) $addOnId;
        }

        $name = trim((string) ($item['new_name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'items' => [translate('New addon name is required.')],
            ]);
        }

        $existing = $this->addOn
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $addon = new AddOn();
        $addon->name = $name;
        $addon->price = (float) ($item['new_price'] ?? 0);
        $addon->tax = 0;
        $addon->save();

        return (int) $addon->id;
    }

    private function validateItemSelectionRules(array $builtItems, Request $request): bool|string
    {
        $itemIds = collect($builtItems)->pluck('add_on_id')->filter();
        if ($itemIds->count() !== $itemIds->unique()->count()) {
            return translate('Duplicate addon selected in template items.');
        }

        $itemsCount = $itemIds->count();
        $min = (int) $request->min_select;
        $max = (int) $request->max_select;

        if ($request->selection_type === 'single') {
            if ($max !== 1) {
                return translate('For single selection templates, max selection must be 1.');
            }
            if (!in_array($min, [0, 1], true)) {
                return translate('For single selection templates, min selection must be 0 or 1.');
            }
        }

        if ($min > $max) {
            return translate('Minimum selection cannot be greater than maximum selection.');
        }

        if ($max > $itemsCount) {
            return translate('Maximum selection cannot be greater than total template items.');
        }

        if ($max > 0) {
            foreach ($builtItems as $item) {
                if (!empty($item['max_qty']) && (int) $item['max_qty'] > $max) {
                    return translate('Per-addon max quantity cannot exceed template maximum selection.');
                }
            }
        }

        return true;
    }
}
