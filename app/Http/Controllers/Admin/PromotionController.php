<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Product;
use App\Model\Promotion;
use App\Model\PromotionGroupProduct;
use App\Model\PromotionItemGroup;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    public function __construct(
        private Promotion $promotion,
        private Product $product
    ) {}

    public function index(Request $request): Renderable
    {
        $search = $request->get('search');
        $promotions = $this->promotion
            ->when($search, function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('headline', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(Helpers::getPagination())
            ->appends(['search' => $search]);

        return view('admin-views.promotion.index', compact('promotions', 'search'));
    }

    public function create(): Renderable
    {
        $products = $this->product->orderBy('name')->get(['id', 'name']);
        return view('admin-views.promotion.create', compact('products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate($this->validationRules());

        DB::transaction(function () use ($request) {
            $promotion = $this->savePromotion(new Promotion(), $request);
            $this->syncItemGroups($promotion, $request);
        });

        Toastr::success(translate('Promotion added successfully'));
        return redirect()->route('admin.promotion-deal.list');
    }

    public function edit(int $id): Renderable
    {
        $promotion = $this->promotion->with('itemGroups.groupProducts')->findOrFail($id);
        $products = $this->product->orderBy('name')->get(['id', 'name']);

        $group1Products = [];
        $group2Products = [];
        foreach ($promotion->itemGroups as $group) {
            $ids = $group->groupProducts->pluck('product_id')->all();
            if ((int) $group->group_number === 1) {
                $group1Products = $ids;
            } else {
                $group2Products = $ids;
            }
        }

        return view('admin-views.promotion.edit', compact(
            'promotion',
            'products',
            'group1Products',
            'group2Products'
        ));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate($this->validationRules());

        DB::transaction(function () use ($request, $id) {
            $promotion = $this->promotion->findOrFail($id);
            $this->savePromotion($promotion, $request);
            $promotion->itemGroups()->each(function (PromotionItemGroup $group) {
                $group->groupProducts()->delete();
                $group->delete();
            });
            $this->syncItemGroups($promotion, $request);
        });

        Toastr::success(translate('Promotion updated successfully'));
        return redirect()->route('admin.promotion-deal.list');
    }

    public function status(Request $request): RedirectResponse
    {
        $promotion = $this->promotion->findOrFail($request->id);
        $promotion->status = $request->status;
        $promotion->save();

        Toastr::success(translate('Promotion status updated'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $promotion = $this->promotion->findOrFail($request->id);
        if ($promotion->image) {
            Helpers::delete('promotion/' . $promotion->image);
        }
        $promotion->delete();

        Toastr::success(translate('Promotion removed'));
        return back();
    }

    private function validationRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'headline' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:bogo,buy_get_discount',
            'discount_cheapest_percent' => 'required|integer|min:0|max:100',
            'discount_expensive_percent' => 'required|integer|min:0|max:100',
            'customer_type' => 'required|in:any,new,returning',
            'order_type' => 'required|in:any,take_away,delivery',
            'group_1_products' => 'required|array|min:1',
            'group_1_products.*' => 'integer|exists:products,id',
            'group_2_products' => 'nullable|array',
            'group_2_products.*' => 'integer|exists:products,id',
        ];
    }

    private function savePromotion(Promotion $promotion, Request $request): Promotion
    {
        $promotion->title = $request->title;
        $promotion->headline = $request->headline;
        $promotion->description = $request->description;
        $promotion->type = $request->type;
        $promotion->discount_cheapest_percent = $request->discount_cheapest_percent;
        $promotion->discount_expensive_percent = $request->discount_expensive_percent;
        $promotion->charge_modifier_addons = $request->boolean('charge_modifier_addons');
        $promotion->customer_type = $request->customer_type;
        $promotion->order_type = $request->order_type;
        $promotion->once_per_customer = $request->boolean('once_per_customer');
        $promotion->is_exclusive = $request->boolean('is_exclusive');
        $promotion->start_date = $request->start_date ?: null;
        $promotion->end_date = $request->end_date ?: null;
        $promotion->highlight_group = $request->highlight_group ?? 1;
        $promotion->status = $request->boolean('status', true) ? 1 : 0;

        if ($request->hasFile('image')) {
            $promotion->image = Helpers::upload('promotion/', 'png', $request->file('image'));
        }

        $promotion->save();
        return $promotion;
    }

    private function syncItemGroups(Promotion $promotion, Request $request): void
    {
        $groups = [
            1 => [
                'label' => $request->group_1_label ?: translate('Item Group 1'),
                'products' => $request->group_1_products ?? [],
            ],
            2 => [
                'label' => $request->group_2_label ?: translate('Item Group 2'),
                'products' => $request->group_2_products ?? [],
            ],
        ];

        foreach ($groups as $groupNumber => $groupData) {
            if ($groupNumber === 2 && empty($groupData['products'])) {
                continue;
            }

            $group = PromotionItemGroup::create([
                'promotion_id' => $promotion->id,
                'group_number' => $groupNumber,
                'label' => $groupData['label'],
            ]);

            foreach ($groupData['products'] as $productId) {
                PromotionGroupProduct::create([
                    'promotion_item_group_id' => $group->id,
                    'product_id' => $productId,
                ]);
            }
        }
    }
}
