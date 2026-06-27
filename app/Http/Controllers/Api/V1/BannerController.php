<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Model\Banner;
use App\Services\PromoOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(
        private Banner $banner,
        private PromoOrderService $promoOrderService,
    ) {}

    public function getBanners(Request $request): JsonResponse
    {
        $branchId = (int) ($request->header('branch-id') ?? 0);

        $banners = $this->banner
            ->with(['groupItems.product.rating', 'groupItems.product.branch_product'])
            ->active()
            ->currentlyValid()
            ->get();

        $formatted = $banners
            ->map(fn (Banner $banner) => $this->promoOrderService->formatBannerForApi($banner, $branchId))
            ->values();

        return response()->json($formatted, 200);
    }
}
