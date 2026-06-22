<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Model\ChefBranch;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Services\OrderStatusService;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use function App\CentralLogics\translate;

class KitchenController extends Controller
{
    public function __construct(
        private ChefBranch        $chefBranch,
        private Branch            $branch,
        private Order             $order,
        private User              $user,
        private OrderDetail       $orderDetail,
        private OrderStatusService $orderStatusService,
    )
    {}

    private function resolveBranchId(): ?int
    {
        $chefBranch = $this->chefBranch->where('user_id', auth()->user()->id)->first();

        return $chefBranch?->branch_id;
    }

    private function attachItemsSummary(LengthAwarePaginator $orders): LengthAwarePaginator
    {
        $orders->getCollection()->transform(function ($order) {
            $itemsSummary = $order->details->map(function ($detail) {
                $productDetails = $detail->product_details;
                if (!is_array($productDetails)) {
                    $productDetails = json_decode($productDetails, true) ?? [];
                }

                return [
                    'quantity' => (int) $detail->quantity,
                    'name' => $productDetails['name'] ?? '',
                ];
            })->values();

            $order->setAttribute('items_summary', $itemsSummary);
            $order->makeHidden(['details']);

            return $order;
        });

        return $orders;
    }

    private function paginateKitchenOrders($query, int $limit, int $offset): LengthAwarePaginator
    {
        return $this->attachItemsSummary(
            $query
                ->with([
                    'details:id,order_id,quantity,product_details',
                    'customer:id,f_name,l_name',
                    'guest:id',
                ])
                ->latest()
                ->paginate($limit, ['*'], 'page', $offset)
        );
    }

    /**
     * @return JsonResponse
     */
    public function getOrderCounts(): JsonResponse
    {
        $branchId = $this->resolveBranchId();
        if (!$branchId) {
            return response()->json([
                'errors' => [
                    ['code' => 'branch', 'message' => translate('Branch not found')]
                ]
            ], 404);
        }

        return response()->json([
            'pending' => $this->order
                ->where('branch_id', $branchId)
                ->whereIn('order_status', ['pending', 'confirmed'])
                ->count(),
            'cooking' => $this->order
                ->where(['order_status' => 'cooking', 'branch_id' => $branchId])
                ->count(),
            'done' => $this->order
                ->where(['order_status' => 'done', 'branch_id' => $branchId])
                ->count(),
        ], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderList(Request $request): JsonResponse
    {
        $limit = is_null($request['limit']) ? 10 : $request['limit'];
        $offset = is_null($request['offset']) ? 1 : $request['offset'];

        $chefBranch = $this->chefBranch->where('user_id', auth()->user()->id)->first();

        $orders = $this->order->with('table')
            ->whereIn('order_status', ['pending', 'confirmed', 'cooking'])
            ->where('branch_id', $chefBranch->branch_id)
            ->latest()
            ->paginate($limit, ['*'], 'page', $offset);

        return response()->json($orders, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $chefBranch = $this->chefBranch->where('user_id', auth()->user()->id)->first();
        $branchId = $chefBranch->branch_id;

        $search = $request['search'];
        $key = explode(' ', $request['search']);

        $orders = $this->paginateKitchenOrders(
            $this->order
                ->where('branch_id', $branchId)
                ->whereIn('order_status', ['pending', 'confirmed', 'cooking', 'done'])
                ->when($search != null, function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->Where('id', 'like', "%{$value}%");
                    }
                }),
            Helpers::getPagination(),
            1
        );

        return response()->json($orders, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function filterByStatus(Request $request): JsonResponse
    {
        $limit = is_null($request['limit']) ? 10 : $request['limit'];
        $offset = is_null($request['offset']) ? 1 : $request['offset'];

        $chefBranch = $this->chefBranch->where('user_id', auth()->user()->id)->first();
        $branchId = $chefBranch->branch_id;

        $orderStatus = $request->order_status;
        if ($orderStatus == 'cooking') {
            $orders = $this->paginateKitchenOrders(
                $this->order->where(['order_status' => $orderStatus, 'branch_id' => $branchId]),
                $limit,
                $offset
            );
        } elseif ($orderStatus == 'pending') {
            $orders = $this->paginateKitchenOrders(
                $this->order
                    ->where('branch_id', $branchId)
                    ->whereIn('order_status', ['pending', 'confirmed']),
                $limit,
                $offset
            );
        } else {
            $orders = $this->paginateKitchenOrders(
                $this->order->where(['order_status' => $orderStatus, 'branch_id' => $branchId]),
                $limit,
                $offset
            );
        }

        return response()->json($orders, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderDetails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $order = $this->order->with('table')->where(['id' => $request->order_id])->first();
        if (isset($order)) {
            $details = $this->orderDetail->where(['order_id' => $order->id])->get();
            $details = isset($details) ? Helpers::order_details_formatter($details) : null;

            return response()->json([
                'order' => $order,
                'details' => $details
            ], 200);

        } else {
            return response()->json([
                'message' => 'no order found'
            ]);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function changeStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'order_status' => 'required|in:cooking,done',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $order = $this->order->with(['customer', 'delivery_man', 'guest'])->find($request->order_id);
        if (!$order) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('no order found')]
                ]
            ], 404);
        }

        $chefBranch = $this->chefBranch->where('user_id', auth()->user()->id)->first();
        if (!$chefBranch || (int) $order->branch_id !== (int) $chefBranch->branch_id) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('Order not found')]
                ]
            ], 403);
        }

        $oldStatus = $order->order_status;
        $newStatus = $request->order_status;

        $allowedFromPending = in_array($oldStatus, ['pending', 'confirmed'], true);
        if ($newStatus === 'cooking' && !$allowedFromPending) {
            return response()->json([
                'errors' => [
                    ['code' => 'order_status', 'message' => translate('Invalid status transition')]
                ]
            ], 403);
        }

        if ($newStatus === 'done' && $oldStatus !== 'cooking') {
            return response()->json([
                'errors' => [
                    ['code' => 'order_status', 'message' => translate('Invalid status transition')]
                ]
            ], 403);
        }

        if ($newStatus === 'cooking') {
            $prepValidator = Validator::make($request->all(), [
                'preparation_time' => 'required|integer|min:1',
            ]);
            if ($prepValidator->fails()) {
                return response()->json(['errors' => Helpers::error_processor($prepValidator)], 403);
            }

            $preparationMinutes = (int) $request->preparation_time;
            $readyAt = Carbon::now()->addMinutes($preparationMinutes);

            $order->update([
                'preparation_time' => $preparationMinutes,
                'delivery_date' => $readyAt->format('Y-m-d'),
                'delivery_time' => $readyAt->format('H:i:s'),
                'order_status' => 'cooking',
                'cooking_started_at' => Carbon::now(),
            ]);

            $order->refresh();

            if ($order->order_status === 'cooking') {
                $this->orderStatusService->notifyOrderCustomerForStatus($order, 'cooking');
                return response()->json(['orders' => $order, 'message' => translate('Order status updated!')], 200);
            }

            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('Status did not changed')]
                ]
            ], 401);
        }

        if ($newStatus === 'done') {
            if ($this->orderStatusService->markOrderDone($order)) {
                return response()->json(['orders' => $order->fresh(), 'message' => translate('Order status updated!')], 200);
            }

            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('Status did not changed')]
                ]
            ], 401);
        }

        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('Status did not changed')]
            ]
        ], 401);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function completeOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $order = $this->order->with(['customer', 'delivery_man', 'guest', 'transaction'])->find($request->order_id);
        if (!$order) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('no order found')]
                ]
            ], 404);
        }

        $chefBranch = $this->chefBranch->where('user_id', auth()->user()->id)->first();
        if (!$chefBranch || (int) $order->branch_id !== (int) $chefBranch->branch_id) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('Order not found')]
                ]
            ], 403);
        }

        $result = $this->orderStatusService->completeOrderFromKitchen($order);
        if (!$result['success']) {
            return response()->json([
                'errors' => [
                    ['code' => $result['code'] ?? 'order', 'message' => $result['message'] ?? translate('Status did not changed')]
                ]
            ], 403);
        }

        return response()->json([
            'order' => $result['order'],
            'message' => translate('Order status updated!'),
        ], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $kitchen = $this->user->find(auth()->user()->id);

        if (!isset($kitchen)) {
            return response()->json([
                'errors' => [
                    ['code' => 'Kitchen', 'message' => translate('Invalid token!')]
                ]
            ], 401);
        }

        $kitchen->cm_firebase_token = $request->token;
        $kitchen->update();

        return response()->json(['kitchen' => $kitchen, 'message' => translate('successfully updated!')], 200);
    }

    /**
     * @return JsonResponse
     */
    public function getProfile(): JsonResponse
    {
        $kitchen = $this->user->find(auth()->user()->id);
        $chefBranch = $this->chefBranch->where('user_id', auth()->user()->id)->first();
        $branch = $this->branch->where('id', $chefBranch->branch_id)->first();

        return response()->json([
            'profile' => $kitchen,
            'branch' => $branch
        ], 200);
    }

}
