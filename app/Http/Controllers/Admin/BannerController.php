<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Banner;
use App\Model\Product;
use App\Services\BannerPromoService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(
        private Banner $banner,
        private Product $product,
        private BannerPromoService $bannerPromoService,
    ) {}

    public function index(): Renderable
    {
        return $this->list(request());
    }

    public function list(Request $request): Renderable
    {
        $search = $request->search;
        $products = $this->product->orderBy('name')->get(['id', 'name']);
        $paymentMethods = $this->paymentMethodOptions();

        $banners = $this->banner
            ->when($search, function ($query) use ($search) {
                $keywords = explode(' ', $search);
                foreach ($keywords as $keyword) {
                    $query->where(function ($inner) use ($keyword) {
                        $inner->where('title', 'LIKE', "%$keyword%")
                            ->orWhere('headline', 'LIKE', "%$keyword%")
                            ->orWhere('id', 'LIKE', "%$keyword%");
                    });
                }
            })
            ->latest()
            ->paginate(Helpers::getPagination())
            ->appends(['search' => $search]);

        return view('admin-views.banner.list', compact('banners', 'search', 'products', 'paymentMethods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['image' => 'required']);

        $image = Helpers::upload('banner/', 'png', $request->file('image'));
        $this->bannerPromoService->store($request, $image);

        Toastr::success(translate('Banner added successfully!'));
        return redirect('admin/banner/list');
    }

    public function edit($id): Renderable
    {
        $banner = $this->banner->with('groupItems.product')->findOrFail($id);
        $products = $this->product->orderBy('name')->get(['id', 'name']);
        $paymentMethods = $this->paymentMethodOptions();

        return view('admin-views.banner.edit', compact('banner', 'products', 'paymentMethods'));
    }

    public function status(Request $request): RedirectResponse
    {
        $banner = $this->banner->findOrFail($request->id);
        $banner->status = $request->status;
        $banner->save();

        Toastr::success(translate('Banner status updated!'));
        return back();
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $banner = $this->banner->findOrFail($id);
        $image = $request->hasFile('image')
            ? Helpers::update('banner/', $banner->image, 'png', $request->file('image'))
            : null;

        $this->bannerPromoService->update($banner, $request, $image);

        Toastr::success(translate('Banner updated successfully!'));
        return redirect('admin/banner/list');
    }

    public function delete(Request $request): RedirectResponse
    {
        $banner = $this->banner->findOrFail($request->id);
        if ($banner->image) {
            Helpers::delete('banner/' . $banner->image);
        }
        $banner->delete();

        Toastr::success(translate('Banner removed!'));
        return back();
    }

    public function productVariations(int $productId): JsonResponse
    {
        $product = $this->product->findOrFail($productId);
        $variations = json_decode($product->variations ?? '[]', true) ?: [];

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variations' => $variations,
        ]);
    }

    private function paymentMethodOptions(): array
    {
        return [
            'cash_on_delivery' => translate('cash_on_delivery'),
            'wallet_payment' => translate('wallet_payment'),
            'digital_payment' => translate('digital_payment'),
            'offline_payment' => translate('offline_payment'),
        ];
    }
}
