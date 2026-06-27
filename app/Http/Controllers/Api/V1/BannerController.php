<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Banner;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    public function __construct(
        private Banner $banner
    )
    {
    }

    /**
     * @return JsonResponse
     */
    public function getBanners(): JsonResponse
    {
        $banners = $this->banner->with([
            'product.rating',
            'product.branch_product',
            'promotion.itemGroups.groupProducts',
        ])->active()->get();

        foreach ($banners as $banner) {
            $banner['product'] = isset($banner['product']) ? Helpers::product_data_formatting($banner['product']) : null;

            if ($banner->promotion) {
                $banner['promotion'] = [
                    'id' => $banner->promotion->id,
                    'title' => $banner->promotion->title,
                    'headline' => $banner->promotion->headline,
                    'type' => $banner->promotion->type,
                    'cta_label' => $banner->cta_label ?? 'Order Now',
                ];
            } else {
                $banner['promotion'] = null;
            }
        }

        return response()->json($banners, 200);
    }
}
