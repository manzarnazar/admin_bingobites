<?php

namespace App\Services;

use App\Model\Banner;
use App\Model\BannerGroupItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BannerPromoService
{
    public function validatePromoRequest(Request $request): array
    {
        return $request->validate([
            'title' => 'required|max:255',
            'headline' => 'required|max:255',
            'description' => 'nullable|max:1000',
            'promotion_type' => 'required|in:bogo,percent_off,fixed_amount',
            'reward_discount_value' => 'required|numeric|min:0',
            'discount_cheapest_percent' => 'nullable|numeric|min:0|max:100',
            'discount_expensive_percent' => 'nullable|numeric|min:0|max:100',
            'charge_paid_addons' => 'nullable|boolean',
            'charge_reward_addons' => 'nullable|boolean',
            'order_type_mode' => 'required|in:any,custom',
            'order_types' => 'nullable|array',
            'order_types.*' => 'in:take_away,delivery',
            'payment_methods' => 'nullable|array',
            'payment_methods.*' => 'string',
            'once_per_customer' => 'nullable|boolean',
            'max_reward_qty' => 'required|integer|min:1',
            'usage_per_customer' => 'nullable|integer|min:1',
            'total_usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'group_1' => 'required|array|min:1',
            'group_1.*.product_id' => 'required|integer|exists:products,id',
            'group_1.*.variations' => 'nullable|array',
            'group_2' => 'required|array|min:1',
            'group_2.*.product_id' => 'required|integer|exists:products,id',
            'group_2.*.variations' => 'nullable|array',
        ]);
    }

    public function fillBannerFromRequest(Banner $banner, Request $request): void
    {
        $banner->title = $request->title;
        $banner->headline = $request->headline;
        $banner->description = $request->description;
        $banner->promotion_type = $request->promotion_type;
        $banner->reward_discount_value = $request->reward_discount_value;
        $banner->discount_cheapest_percent = $request->discount_cheapest_percent;
        $banner->discount_expensive_percent = $request->discount_expensive_percent;
        $banner->charge_paid_addons = $request->boolean('charge_paid_addons');
        $banner->charge_reward_addons = $request->boolean('charge_reward_addons');
        $banner->order_type_mode = $request->order_type_mode;
        $banner->order_types = $request->order_type_mode === 'custom'
            ? array_values($request->input('order_types', []))
            : null;
        $banner->payment_methods = $request->filled('payment_methods')
            ? array_values($request->input('payment_methods', []))
            : null;
        $banner->once_per_customer = $request->boolean('once_per_customer');
        $banner->max_reward_qty = (int) $request->max_reward_qty;
        $banner->usage_per_customer = $request->filled('usage_per_customer')
            ? (int) $request->usage_per_customer
            : null;
        $banner->total_usage_limit = $request->filled('total_usage_limit')
            ? (int) $request->total_usage_limit
            : null;
        $banner->start_date = $request->start_date ?: null;
        $banner->end_date = $request->end_date ?: null;

        if ($banner->promotion_type === Banner::PROMOTION_TYPE_BOGO) {
            $banner->reward_discount_value = 100;
            $banner->discount_cheapest_percent = $banner->discount_cheapest_percent ?? 100;
            $banner->discount_expensive_percent = $banner->discount_expensive_percent ?? 100;
        } elseif ($banner->promotion_type === Banner::PROMOTION_TYPE_PERCENT_OFF) {
            $banner->discount_cheapest_percent = $banner->discount_cheapest_percent ?? $banner->reward_discount_value;
            $banner->discount_expensive_percent = $banner->discount_expensive_percent ?? $banner->reward_discount_value;
        }
    }

    public function syncGroupItems(Banner $banner, array $groupOne, array $groupTwo): void
    {
        $banner->groupItems()->delete();

        $this->insertGroupItems($banner, 1, $groupOne);
        $this->insertGroupItems($banner, 2, $groupTwo);
    }

    private function insertGroupItems(Banner $banner, int $groupNumber, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            BannerGroupItem::create([
                'banner_id' => $banner->id,
                'group_number' => $groupNumber,
                'product_id' => (int) $item['product_id'],
                'variations' => $this->normalizeVariations($item['variations'] ?? []),
                'sort_order' => $index,
            ]);
        }
    }

    public function normalizeVariations(array $variations): array
    {
        $normalized = [];

        foreach ($variations as $variation) {
            if (!is_array($variation) || empty($variation['name'])) {
                continue;
            }

            $labels = $variation['values']['label'] ?? $variation['labels'] ?? [];
            if (!is_array($labels)) {
                $labels = [$labels];
            }

            $labels = array_values(array_filter($labels, fn ($label) => $label !== null && $label !== ''));

            if (empty($labels)) {
                continue;
            }

            $normalized[] = [
                'name' => $variation['name'],
                'values' => ['label' => $labels],
            ];
        }

        return $normalized;
    }

    public function store(Request $request, ?string $imageName): Banner
    {
        return DB::transaction(function () use ($request, $imageName) {
            $data = $this->validatePromoRequest($request);

            $banner = new Banner();
            $this->fillBannerFromRequest($banner, $request);
            $banner->image = $imageName;
            $banner->status = 1;
            $banner->save();

            $this->syncGroupItems($banner, $data['group_1'], $data['group_2']);

            return $banner;
        });
    }

    public function update(Banner $banner, Request $request, ?string $imageName = null): Banner
    {
        return DB::transaction(function () use ($banner, $request, $imageName) {
            $data = $this->validatePromoRequest($request);

            $this->fillBannerFromRequest($banner, $request);
            if ($imageName) {
                $banner->image = $imageName;
            }
            $banner->save();

            $this->syncGroupItems($banner, $data['group_1'], $data['group_2']);

            return $banner;
        });
    }
}
