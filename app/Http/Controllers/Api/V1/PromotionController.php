<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Model\Promotion;
use App\Services\Promotion\PromotionEligibility;
use App\Services\Promotion\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        private Promotion $promotion,
        private PromotionService $promotionService,
        private PromotionEligibility $eligibility
    ) {}

    public function show(int $id): JsonResponse
    {
        $promotion = $this->promotion->with([
            'itemGroups.groupProducts.product',
        ])->active()->find($id);

        if (!$promotion) {
            return response()->json([
                'errors' => [[
                    'code' => 'promotion',
                    'message' => translate('Promotion not found or inactive'),
                ]],
            ], 404);
        }

        return response()->json($this->promotionService->formatForApi($promotion), 200);
    }

    public function eligibility(Request $request, int $id): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'errors' => [[
                    'code' => 'auth',
                    'message' => translate('Please login to use this promotion'),
                ]],
            ], 401);
        }

        $promotion = $this->promotion->active()->find($id);
        if (!$promotion) {
            return response()->json([
                'errors' => [[
                    'code' => 'promotion',
                    'message' => translate('Promotion not found or inactive'),
                ]],
            ], 404);
        }

        $orderType = $request->query('order_type', 'any');
        $result = $this->eligibility->check($promotion, (int) $user->id, $orderType);

        return response()->json([
            'eligible' => $result['eligible'],
            'reason' => $result['reason'],
            'promotion' => $this->promotionService->formatForApi($promotion),
        ], 200);
    }
}
