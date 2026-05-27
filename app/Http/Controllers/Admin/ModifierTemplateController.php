<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\AddOn;
use App\Model\ModifierTemplate;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModifierTemplateController extends Controller
{
    public function __construct(
        private ModifierTemplate $modifierTemplate,
        private AddOn $addOn
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
            ->orderByDesc('id')
            ->paginate(Helpers::getPagination())
            ->appends($queryParam);

        $addons = $this->addOn->orderBy('name')->get();
        return view('admin-views.modifier-template.index', compact('templates', 'addons', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|unique:modifier_templates,name',
            'selection_type' => 'required|in:single,multi',
            'min_select' => 'required|integer|min:0',
            'max_select' => 'required|integer|min:0',
            'items' => 'required|array|min:1',
            'items.*.add_on_id' => 'required|exists:add_ons,id',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $validated = $this->validateItemSelectionRules($request);
        if ($validated !== true) {
            Toastr::error($validated);
            return back()->withInput();
        }

        DB::transaction(function () use ($request) {
            $template = $this->modifierTemplate->create([
                'name' => $request->name,
                'description' => $request->description,
                'selection_type' => $request->selection_type,
                'min_select' => (int) $request->min_select,
                'max_select' => (int) $request->max_select,
                'is_required' => $request->has('is_required') ? 1 : 0,
                'is_active' => $request->has('is_active') ? 1 : 0,
                'created_by' => auth('admin')->id(),
            ]);

            $items = [];
            foreach ($request->items as $index => $item) {
                $items[] = [
                    'add_on_id' => $item['add_on_id'],
                    'sort_order' => $item['sort_order'] ?? $index,
                    'is_default' => isset($item['is_default']) ? 1 : 0,
                    'is_active' => isset($item['is_active']) ? 1 : 0,
                ];
            }
            $template->items()->createMany($items);
        });

        Toastr::success(translate('Modifier template created successfully!'));
        return back();
    }

    public function edit($id): Renderable
    {
        $template = $this->modifierTemplate->with('items')->findOrFail($id);
        $addons = $this->addOn->orderBy('name')->get();
        return view('admin-views.modifier-template.edit', compact('template', 'addons'));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'name' => 'required|unique:modifier_templates,name,' . $id,
            'selection_type' => 'required|in:single,multi',
            'min_select' => 'required|integer|min:0',
            'max_select' => 'required|integer|min:0',
            'items' => 'required|array|min:1',
            'items.*.add_on_id' => 'required|exists:add_ons,id',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $validated = $this->validateItemSelectionRules($request);
        if ($validated !== true) {
            Toastr::error($validated);
            return back()->withInput();
        }

        DB::transaction(function () use ($request, $id) {
            $template = $this->modifierTemplate->findOrFail($id);
            $template->update([
                'name' => $request->name,
                'description' => $request->description,
                'selection_type' => $request->selection_type,
                'min_select' => (int) $request->min_select,
                'max_select' => (int) $request->max_select,
                'is_required' => $request->has('is_required') ? 1 : 0,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);

            $template->items()->delete();

            $items = [];
            foreach ($request->items as $index => $item) {
                $items[] = [
                    'add_on_id' => $item['add_on_id'],
                    'sort_order' => $item['sort_order'] ?? $index,
                    'is_default' => isset($item['is_default']) ? 1 : 0,
                    'is_active' => isset($item['is_active']) ? 1 : 0,
                ];
            }
            $template->items()->createMany($items);
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

    private function validateItemSelectionRules(Request $request): bool|string
    {
        $itemIds = collect($request->items)->pluck('add_on_id')->filter();
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

        return true;
    }
}
