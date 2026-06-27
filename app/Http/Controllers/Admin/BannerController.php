<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Banner;
use App\Model\Category;
use App\Model\Product;
use App\Model\Promotion;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(
        private Banner   $banner,
        private Product  $product,
        private Category $category,
        private Promotion $promotion
    )
    {}

    function index(): Renderable
    {
        $products = $this->product->orderBy('name')->get();
        $categories = $this->category->where(['parent_id' => 0])->orderBy('name')->get();
        $promotions = $this->promotion->where('status', 1)->orderBy('title')->get(['id', 'title', 'headline']);

        return view('admin-views.banner.index', compact('products', 'categories', 'promotions'));
    }

    function list(Request $request): Renderable
    {
        $search = $request->search;
        $queryParam = ['search' => $search];

        $banners = $this->banner
            ->when($search, function ($query) use ($search, $queryParam) {
                $keywords = explode(' ', $search);
                foreach ($keywords as $keyword) {
                    $query->orWhere('title', 'LIKE', "%$keyword%")
                        ->orwhere('id', 'LIKE', "%$keyword%");
                }
            })
            ->latest()
            ->paginate(Helpers::getPagination())
            ->appends($queryParam);

        return view('admin-views.banner.list', compact('banners', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => 'required|max:255',
            'image' => 'required',
            'link_type' => 'required|in:none,product,category,promotion',
        ], [
            'title.max' => translate('Title is too long'),
        ]);

        $banner = $this->banner;
        $banner->title = $request->title;
        $banner->headline = $request->headline;
        $banner->cta_label = $request->cta_label ?: 'Order Now';
        $banner->link_type = $request->link_type;

        $this->applyLinkType($banner, $request);

        $banner->image = Helpers::upload('banner/', 'png', $request->file('image'));
        $banner->save();

        Toastr::success(translate('Banner added successfully!'));
        return redirect('admin/banner/list');
    }

    public function edit($id): Renderable
    {
        $products = $this->product->orderBy('name')->get();
        $banner = $this->banner->find($id);
        $categories = $this->category->where(['parent_id' => 0])->orderBy('name')->get();
        $promotions = $this->promotion->where('status', 1)->orderBy('title')->get(['id', 'title', 'headline']);

        return view('admin-views.banner.edit', compact('banner', 'products', 'categories', 'promotions'));
    }

    public function status(Request $request): RedirectResponse
    {
        $banner = $this->banner->find($request->id);
        $banner->status = $request->status;
        $banner->save();

        Toastr::success(translate('Banner status updated!'));
        return back();
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'title' => 'required|max:255',
            'link_type' => 'required|in:none,product,category,promotion',
        ], [
            'title.max' => translate('Title is too long!'),
        ]);

        $banner = $this->banner->find($id);
        $banner->title = $request->title;
        $banner->headline = $request->headline;
        $banner->cta_label = $request->cta_label ?: 'Order Now';
        $banner->link_type = $request->link_type;

        $this->applyLinkType($banner, $request);

        $banner->image = $request->has('image') ? Helpers::update('banner/', $banner->image, 'png', $request->file('image')) : $banner->image;
        $banner->save();

        Toastr::success(translate('Banner updated successfully!'));
        return redirect('admin/banner/list');
    }

    public function delete(Request $request): RedirectResponse
    {
        $banner = $this->banner->find($request->id);
        Helpers::delete('banner/' . $banner['image']);
        $banner->delete();

        Toastr::success(translate('Banner removed!'));
        return back();
    }

    private function applyLinkType(Banner $banner, Request $request): void
    {
        $banner->product_id = null;
        $banner->category_id = null;
        $banner->promotion_id = null;

        switch ($request->link_type) {
            case 'product':
                $banner->product_id = $request->product_id;
                break;
            case 'category':
                $banner->category_id = $request->category_id;
                break;
            case 'promotion':
                $banner->promotion_id = $request->promotion_id;
                break;
        }
    }
}
