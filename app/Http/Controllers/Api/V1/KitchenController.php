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

        $orders = $this->order
            ->where('branch_id', $branchId)
            ->whereIn('order_status', ['pending', 'confirmed', 'cooking', 'done'])
            ->when($search != null, function ($query) use ($key) {
                foreach ($key as $value) {
                    $query->Where('id', 'like', "%{$value}%");
                }
            })
            ->latest()
            ->paginate(Helpers::getPagination());

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
            $orders = $this->order
                ->where(['order_status' => $orderStatus, 'branch_id' => $branchId])
                ->latest()
                ->paginate($limit, ['*'], 'page', $offset);

        } elseif ($orderStatus == 'pending') {
            $orders = $this->order
                ->where('branch_id', $branchId)
                ->whereIn('order_status', ['pending', 'confirmed'])
                ->latest()
                ->paginate($limit, ['*'], 'page', $offset);

        } else {
            $orders = $this->order
                ->where(['order_status' => $orderStatus, 'branch_id' => $branchId])
                ->latest()
                ->paginate($limit, ['*'], 'page', $offset);
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
            $order->preparation_time = $preparationMinutes;
            $readyAt = Carbon::now()->addMinutes($preparationMinutes);
            $order->delivery_date = $readyAt->format('Y-m-d');
            $order->delivery_time = $readyAt->format('H:i:s');
            $order->order_status = 'cooking';

            if ($order->update()) {
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
