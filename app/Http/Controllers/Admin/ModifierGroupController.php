<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Category;
use App\Model\ModifierGroup;
use App\Model\Product;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModifierGroupController extends Controller
{
    public function __construct(
        private ModifierGroup $modifierGroup,
        private Product $product,
        private Category $category
    ) {}

    public function index(Request $request): Renderable
    {
        $search = $request->get('search');
        $queryParam = [];

        $groups = $this->modifierGroup->withCount(['options', 'products'])
            ->when($search, function ($query) use ($search, &$queryParam) {
                $queryParam['search'] = $search;
                $key = explode(' ', $search);
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(Helpers::getPagination())
            ->appends($queryParam);

        $allGroups = $this->modifierGroup->orderBy('name')->get(['id', 'name']);
        $categories = $this->category->where(['position' => 0])->get();

        return view('admin-views.modifier-group.index', compact('groups', 'search', 'categories', 'allGroups'));
    }

    public function create(): Renderable
    {
        return view('admin-views.modifier-group.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $group = $this->modifierGroup->create($data['group']);
            $group->options()->createMany($data['options']);
        });

        Toastr::success(translate('Modifier group added successfully!'));
        return redirect()->route('admin.modifier-group.index');
    }

    public function edit(int $id): Renderable
    {
        $group = $this->modifierGroup->with('options')->findOrFail($id);
        return view('admin-views.modifier-group.edit', compact('group'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $group = $this->modifierGroup->findOrFail($id);
        $data = $this->validateData($request, $id);

        DB::transaction(function () use ($group, $data) {
            $group->update($data['group']);
            $group->options()->delete();
            $group->options()->createMany($data['options']);
        });

        Toastr::success(translate('Modifier group updated successfully!'));
        return redirect()->route('admin.modifier-group.index');
    }

    public function delete(int $id): RedirectResponse
    {
        $group = $this->modifierGroup->findOrFail($id);
        $group->delete();
        Toastr::success(translate('Modifier group removed!'));
        return back();
    }

    public function bulkAttach(Request $request): RedirectResponse
    {
        $request->validate([
            'modifier_group_id' => 'required|exists:modifier_groups,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        $group = $this->modifierGroup->findOrFail($request->modifier_group_id);
        $productIds = $request->product_ids ?? [];

        if (empty($productIds) && $request->filled('category_id')) {
            $categoryId = (string) $request->category_id;
            $productIds = $this->product->whereJsonContains('category_ids', [['id' => $categoryId]])->pluck('id')->all();
        }

        if (empty($productIds)) {
            Toastr::error(translate('Please select products or category.'));
            return back();
        }

        $syncData = [];
        $position = 0;

        foreach ($productIds as $productId) {
            $syncData[$productId] = [
                'selection_type' => $group->selection_type,
                'min' => $group->min,
                'max' => $group->max,
                'is_required' => $group->is_required,
                'position' => $position++,
                'is_default_enabled' => 1,
            ];
        }

        $group->products()->syncWithoutDetaching($syncData);

        Toastr::success(translate('Modifier group attached to selected products.'));
        return back();
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        $request->validate([
            'name' => 'required|max:255|unique:modifier_groups,name,' . $id,
            'selection_type' => 'required|in:single,multi',
            'min' => 'nullable|integer|min:0',
            'max' => 'nullable|integer|min:0',
            'is_required' => 'nullable|in:on',
            'is_active' => 'nullable|in:on',
            'options' => 'required|array|min:1',
            'options.*.name' => 'required|max:255',
            'options.*.additional_price' => 'nullable|numeric|min:0',
        ]);

        $groupData = [
            'name' => $request->name,
            'description' => $request->description,
            'selection_type' => $request->selection_type,
            'min' => (int) ($request->min ?? 0),
            'max' => (int) ($request->max ?? 0),
            'is_required' => $request->boolean('is_required'),
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($groupData['selection_type'] === 'single') {
            $groupData['min'] = 0;
            $groupData['max'] = 0;
        }

        if ($groupData['selection_type'] === 'multi' && $groupData['max'] > 0 && $groupData['min'] >= $groupData['max']) {
            throw ValidationException::withMessages([
                'max' => translate('maximum_value_can_not_be_smaller_or_equal_then_minimum_value'),
            ]);
        }

        $options = [];
        foreach (array_values($request->options) as $index => $option) {
            $options[] = [
                'name' => $option['name'],
                'additional_price' => $option['additional_price'] ?? 0,
                'position' => $index,
                'is_active' => true,
            ];
        }

        return [
            'group' => $groupData,
            'options' => $options,
        ];
    }
}
