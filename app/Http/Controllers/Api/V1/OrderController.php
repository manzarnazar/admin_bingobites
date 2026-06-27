<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Model\BusinessSetting;
use App\Model\CustomerAddress;
use App\Model\DMReview;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\Product;
use App\Model\ProductByBranch;
use App\Models\GuestUser;
use App\Models\OfflinePayment;
use App\Models\OrderPartialPayment;
use App\Models\OrderArea;
use App\Services\PromoOrderService;
use App\Traits\CalculateOrderDataTrait;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use function App\CentralLogics\translate;

class OrderController extends Controller
{
    use CalculateOrderDataTrait;
    public function __construct(
        private User            $user,
        private Order           $order,
        private OrderDetail     $order_detail,
        private ProductByBranch $product_by_branch,
        private Product         $product,
        private OfflinePayment  $offlinePayment,
        private BusinessSetting $business_setting,
        private OrderArea       $orderArea,
    ){}

    /**
     * Safely get authenticated user without initializing Passport when no token is present
     */
    private function getAuthenticatedUser(Request $request)
    {
        try {
            if ($request->bearerToken()) {
                return auth('api')->user();
            }
        } catch (\Exception $e) {
            // Passport keys not configured, treat as guest
        }
        return null;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function trackOrder(Request $request): JsonResponse
    {
        // Check if user is authenticated safely without initializing Passport
        $authenticatedUser = null;
        try {
            if ($request->bearerToken()) {
                $authenticatedUser = auth('api')->user();
            }
        } catch (\Exception $e) {
            // Passport keys not configured, treat as guest
            $authenticatedUser = null;
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'guest_id' => $authenticatedUser ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId = $authenticatedUser ? $authenticatedUser->id : $request['guest_id'];
        $userType = $authenticatedUser ? 0 : 1;

        $order = $this->order->where(['id' => $request['order_id'], 'user_id' => $userId, 'is_guest' => $userType])->first();
        if (!isset($order)) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('Order not found!')]
                ]
            ], 404);
        }

        return response()->json(OrderLogic::track_order($request['order_id']), 200);
    }

    /**
     * Preview order totals using the same calculation as payment / place order.
     */
    public function previewAmount(Request $request): JsonResponse
    {
        $authenticatedUser = $this->getAuthenticatedUser($request);

        $validator = Validator::make($request->all(), [
            'cart' => 'required|array|min:1',
            'branch_id' => 'required',
            'order_type' => 'required|in:take_away,delivery,dine_in',
            'distance' => 'required|numeric',
            'coupon_code' => 'nullable|string',
            'guest_id' => $authenticatedUser ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $userId = $authenticatedUser?->id ?? $request->input('guest_id');
        $isGuest = $authenticatedUser ? 0 : 1;

        $deliveryChargeInfo = [
            'branch_id' => $request->input('branch_id'),
            'distance' => $request->input('distance'),
            'selected_delivery_area' => $request->input('selected_delivery_area'),
            'order_type' => $request->input('order_type'),
        ];

        try {
            $amountData = $this->calculateOrderAmount(
                cart: $request->input('cart'),
                userId: $userId,
                isGuest: $isGuest,
                deliveryChargeInfo: $deliveryChargeInfo,
                couponCode: $request->input('coupon_code'),
                promotionId: $request->filled('promotion_id') ? (int) $request->input('promotion_id') : null,
                paymentMethod: $request->input('payment_method'),
            );
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 403);
        } catch (\Exception $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 403);
        }

        if ($amountData instanceof JsonResponse) {
            return $amountData;
        }

        $orderAmount = $amountData['order_amount'] + $amountData['delivery_charge_amount'];

        return response()->json([
            'order_amount' => Helpers::set_price($orderAmount),
            'total_product_price' => Helpers::set_price($amountData['total_product_price']),
            'total_addon_price' => Helpers::set_price($amountData['total_addon_price']),
            'product_discount' => Helpers::set_price($amountData['total_discount_on_product']),
            'promotion_discount_amount' => Helpers::set_price($amountData['promotion_discount_amount'] ?? 0),
            'tax_amount' => Helpers::set_price($amountData['total_product_and_addon_tax_amount']),
            'referral_discount_amount' => Helpers::set_price($amountData['referral_discount_amount']),
            'coupon_discount_amount' => Helpers::set_price($amountData['coupon_discount_amount']),
            'delivery_charge_amount' => Helpers::set_price($amountData['delivery_charge_amount']),
        ], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function placeOrder(Request $request): JsonResponse
    {
        // Check if user is authenticated safely without initializing Passport
        $authenticatedUser = null;
        try {
            if ($request->bearerToken()) {
                $authenticatedUser = auth('api')->user();
            }
        } catch (\Exception $e) {
            // Passport keys not configured, treat as guest
            $authenticatedUser = null;
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required',
            'order_type' => 'required',
            'branch_id' => 'required',
            'delivery_time' => 'required',
            'delivery_date' => 'required',
            'distance' => 'required',
            'guest_id' => $authenticatedUser ? 'nullable' : 'required',
            'is_partial' => 'required|in:0,1',
            'cart' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        Helpers::update_daily_product_stock();

        if($authenticatedUser){
            $customer = $this->user->find($authenticatedUser->id);
        }
        $userId = $authenticatedUser ? $authenticatedUser->id : $request['guest_id'];
        $userType = $authenticatedUser ? 0 : 1;

        $deliveryChargeInfo = [
            'branch_id' => $request['branch_id'],
            'distance' => $request['distance'],
            'selected_delivery_area' => $request['selected_delivery_area'],
            'order_type' => $request['order_type'],
        ];

        try {
            $amountData = $this->calculateOrderAmount(
                cart: $request['cart'],
                userId : $userId,
                isGuest : $userType,
                deliveryChargeInfo: $deliveryChargeInfo,
                couponCode: $request['coupon_code'] ?? null,
                promotionId: $request->filled('promotion_id') ? (int) $request['promotion_id'] : null,
                paymentMethod: $request['payment_method'] ?? null,
            );

        }  catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 403);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 403);
        }

        $orderAmountWithDeliveryCharge = $amountData['order_amount'] + $amountData['delivery_charge_amount'];

        if ($request->payment_method == 'wallet_payment') {
            if (Helpers::get_business_settings('wallet_status') != 1){
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('customer_wallet_status_is_disable')]]], 403);
            }
            if (isset($customer) && $customer->wallet_balance < $orderAmountWithDeliveryCharge) {
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('you_do_not_have_sufficient_balance_in_wallet')]]], 403);
            }
        }

        if ($request['is_partial'] == 1) {
            if (Helpers::get_business_settings('wallet_status') != 1){
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('customer_wallet_status_is_disable')]]], 403);
            }
            if (isset($customer) && $customer->wallet_balance > $orderAmountWithDeliveryCharge){
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('since your wallet balance is more than order amount, you can not place partial order')]]], 403);
            }
            if (isset($customer) && $customer->wallet_balance < 1){
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('since your wallet balance is less than 1, you can not place partial order')]]], 403);
            }
        }

        $preparationTime = Branch::where(['id' => $request['branch_id']])->first()->preparation_time ?? 0;

        if ($request['delivery_time'] == 'now') {
            $deliveryDate = Carbon::now()->format('Y-m-d');
            $deliveryTime = Carbon::now()->add($preparationTime, 'minute')->format('H:i:s');
        } else {
            $deliveryDate = $request['delivery_date'];
            $deliveryTime = Carbon::parse($request['delivery_time'])->add($preparationTime, 'minute')->format('H:i:s');
        }

        if ($request->is_partial == 1) {
            $paymentStatus = ($request->payment_method == 'cash_on_delivery' || $request->payment_method == 'offline_payment') ? 'partial_paid' : 'paid';
        } else {
            $paymentStatus = ($request->payment_method == 'cash_on_delivery' || $request->payment_method == 'offline_payment') ? 'unpaid' : 'paid';
        }

        $orderStatus = 'pending';

        try {
            DB::beginTransaction();

            $orderId = Helpers::generate_order_id();
            $order = [
                'id' => $orderId,
                'user_id' => $userId,
                'is_guest' => $userType,
                'order_amount' => Helpers::set_price($amountData['order_amount']),
                'coupon_discount_amount' => Helpers::set_price($amountData['coupon_discount_amount']),
                'coupon_discount_title' => $request->coupon_discount_title ?: null,
                'payment_status' => $paymentStatus,
                'order_status' => $orderStatus,
                'coupon_code' => $amountData['coupon_code'],
                'payment_method' => $request->payment_method,
                'transaction_reference' => $request->transaction_reference ?? null,
                'order_note' => $request['order_note'],
                'order_type' => $request['order_type'],
                'branch_id' => $request['branch_id'],
                'delivery_address_id' => $request->delivery_address_id,
                'delivery_date' => $deliveryDate,
                'delivery_time' => $deliveryTime,
                'delivery_address' => $request->delivery_address_id && ($address = CustomerAddress::find($request->delivery_address_id))
                    ? json_encode($address)
                    : ($request->delivery_address
                        ? json_encode($request->delivery_address)
                        : null),
                'delivery_charge' => $amountData['delivery_charge_amount'],
                'preparation_time' => 0,
                'is_cutlery_required' => $request['is_cutlery_required'] ?? 0,
                'bring_change_amount' => $request->payment_method != 'cash_on_delivery' ? 0 : ($request->bring_change_amount != null ? $request->bring_change_amount : 0),
                'total_tax_amount' => $amountData['total_product_and_addon_tax_amount'],
                'referral_discount' => $amountData['referral_discount_amount'],
                'promotion_id' => $request->filled('promotion_id') ? (int) $request['promotion_id'] : null,
                'promotion_discount_amount' => Helpers::set_price($amountData['promotion_discount_amount'] ?? 0),
                'created_at' => now(),
                'updated_at' => now()
            ];

            $this->order->insertGetId($order);

            foreach ($request['cart'] as $c) {
                $product = $this->product->find($c['product_id']);
                $branchProduct = $this->product_by_branch->where(['product_id' => $c['product_id'], 'branch_id' => $request['branch_id']])->first();

                $discountData = [];
                $product->halal_status = $branchProduct?->halal_status ?? 0;

                if ($branchProduct) {
                    $branchProductVariations = $branchProduct->variations;
                    $variations = [];
                    if (count($branchProductVariations)) {
                        $variation_data = Helpers::get_varient($branchProductVariations, $c['variations']);
                        $price = $branchProduct['price'] + $variation_data['price'];
                        $variations = $variation_data['variations'];
                    } else {
                        $price = $branchProduct['price'];
                    }
                    $discountData = [
                        'discount_type' => $branchProduct['discount_type'],
                        'discount' => $branchProduct['discount'],
                    ];
                } else {
                    $productVariations = json_decode($product->variations, true);
                    $variations = [];
                    if (count($productVariations)) {
                        $variation_data = Helpers::get_varient($productVariations, $c['variations']);
                        $price = $product['price'] + $variation_data['price'];
                        $variations = $variation_data['variations'];
                    } else {
                        $price = $product['price'];
                    }
                    $discountData = [
                        'discount_type' => $product['discount_type'],
                        'discount' => $product['discount'],
                    ];
                }

                $discountOnProduct = Helpers::discount_calculate($discountData, $price);

                $normalizedAddons = Helpers::normalize_order_addons(
                    product: $product,
                    selectedVariations: $c['variations'] ?? [],
                    selectedAddonIds: $c['add_on_ids'] ?? [],
                    selectedAddonQtys: $c['add_on_qtys'] ?? [],
                );

                $orderDetail = [
                    'order_id' => $orderId,
                    'product_id' => $c['product_id'],
                    'product_details' => $product,
                    'quantity' => $c['quantity'],
                    'price' => $price,
                    'tax_amount' => Helpers::new_tax_calculate($product, $price, $discountData),
                    'discount_on_product' => $discountOnProduct,
                    'discount_type' => 'discount_on_product',
                    'variant' => json_encode($c['variant']),
                    'variation' => json_encode($variations),
                    'add_on_ids' => json_encode($normalizedAddons['add_on_ids']),
                    'add_on_qtys' => json_encode($normalizedAddons['add_on_qtys']),
                    'add_on_prices' => json_encode($normalizedAddons['add_on_prices']),
                    'add_on_taxes' => json_encode($normalizedAddons['add_on_taxes']),
                    'add_on_tax_amount' => $normalizedAddons['add_on_tax_amount'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                $this->order_detail->insert($orderDetail);

                $this->product->find($c['product_id'])->increment('popularity_count');

                //daily and fixed stock quantity update
                if($branchProduct->stock_type == 'daily' || $branchProduct->stock_type == 'fixed' ){
                    $branchProduct->sold_quantity += $c['quantity'];
                    $branchProduct->save();
                }
            }

            if ($request->payment_method == 'wallet_payment') {
                $amount = $amountData['order_amount'] + $amountData['delivery_charge_amount'];
                CustomerLogic::create_wallet_transaction(user_id: $order['user_id'], amount: $amount, transaction_type: 'order_place', referance: $order['id']);
            }

            if ($request->payment_method == 'offline_payment') {
                $offlinePayment = $this->offlinePayment;
                $offlinePayment->order_id = $order['id'];
                $offlinePayment->payment_info = json_encode($request['payment_info']);
                $offlinePayment->save();
            }

            if ($request['is_partial'] == 1){
                $totalOrderAmount = $amountData['order_amount'] + $amountData['delivery_charge_amount'];
                $walletAmount = $customer->wallet_balance;
                $dueAmount = $totalOrderAmount - $walletAmount;

                $walletTransaction = CustomerLogic::create_wallet_transaction($order['user_id'], $walletAmount, 'order_place', $order['id']);

                $partial = new OrderPartialPayment;
                $partial->order_id = $order['id'];
                $partial->paid_with = 'wallet_payment';
                $partial->paid_amount = $walletAmount;
                $partial->due_amount = $dueAmount;
                $partial->save();

                if ($request['payment_method'] != 'cash_on_delivery'){
                    $partial = new OrderPartialPayment;
                    $partial->order_id = $order['id'];
                    $partial->paid_with = $request['payment_method'];
                    $partial->paid_amount = $dueAmount;
                    $partial->due_amount = 0;
                    $partial->save();
                }
            }

            if($request['selected_delivery_area']){
                $orderArea = $this->orderArea;
                $orderArea->order_id = $orderId;
                $orderArea->branch_id = $order['branch_id'];
                $orderArea->area_id = $request['selected_delivery_area'];
                $orderArea->save();
            }

            $authenticatedUser = $this->getAuthenticatedUser($request);
            if ($authenticatedUser) {
                $registeredCustomer = $authenticatedUser;

                if ($registeredCustomer?->referral_customer_details && $registeredCustomer?->referral_customer_details->is_used == 0) {
                    $registeredCustomer?->referral_customer_details->update(['is_used' => 1]);

                    $referUser = $this->user->where(['id' => $registeredCustomer?->refer_by])->first();
                    if (isset($referUser) && $amountData['referral_discount_amount'] > 0){
                        $this->sendNotificationToReferralUser(referredUser: $referUser);
                    }
                }
            }

            if (!empty($amountData['promotion_banner'])) {
                app(PromoOrderService::class)->recordUsage(
                    banner: $amountData['promotion_banner'],
                    userId: $userId,
                    isGuest: $userType,
                    orderId: $orderId,
                );
            }

            DB::commit();

            // Send Email & Notification
            $this->orderEmailAndNotification(request: $request, or : $order, order_id: $orderId);

            return response()->json([
                'message' => translate('order_success'),
                'order_id' => $orderId
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'errors' => [[
                    'code' => 'order_place',
                    'message' => $e->getMessage(),
                ]],
            ], 403);
        }
    }

    public function sendNotificationToReferralUser($referredUser)
    {
        $message = Helpers::order_status_update_message('referral_code_user_first_order_place_message');
        $restaurantName = Helpers::get_business_settings('restaurant_name');
        $customerName = ($referredUser->f_name ?? '') . ' ' . ($referredUser->l_name ?? '');
        $local = $referredUser->language_code ?? 'en';

        if ($local != 'en'){
            $translatedMessage = BusinessSetting::with('translations')->where(['key' => 'referral_code_user_first_order_place_message'])->first();
            if (isset($translatedMessage->translations)){
                foreach ($translatedMessage->translations as $translation){
                    if ($local == $translation->locale){
                        $message = $translation->value;
                    }
                }
            }
        }

        $value = Helpers::text_variable_data_format(value:$message, user_name: $customerName, restaurant_name: $restaurantName);
        $customerFcmToken = $referredUser->cm_firebase_token ?? null;

        if ($value && isset($customerFcmToken)) {
            $data = [
                'title' => translate('Referral code user First order place'),
                'description' => $value,
                'order_id' => '',
                'image' => '',
                'type' => 'referral',
            ];

            try {
                Helpers::send_push_notif_to_device($customerFcmToken, $data);
            }catch (\Exception $e) {
                //
            }
        }
    }

    private function orderEmailAndNotification($request, $or, $order_id)
    {
        $authenticatedUser = $this->getAuthenticatedUser($request);
        if ($authenticatedUser){
            $fcmToken = $authenticatedUser->cm_firebase_token ?? null;
            $local = $authenticatedUser->language_code ?? 'en';
            $customerName = ($authenticatedUser->f_name ?? '') . ' '. ($authenticatedUser->l_name ?? '');
        }else{
            $guest = GuestUser::find($request['guest_id']);
            $fcmToken = $guest ? $guest->fcm_token : '';
            $local = 'en';
            $customerName = 'Guest User';
        }

        $message = Helpers::order_status_update_message($or['order_status']);

        if ($local != 'en'){
            $statusKey = Helpers::order_status_message_key($or['order_status']);
            $translatedMessage = $this->business_setting->with('translations')->where(['key' => $statusKey])->first();
            if (isset($translatedMessage->translations)){
                foreach ($translatedMessage->translations as $translation){
                    if ($local == $translation->locale){
                        $message = $translation->value;
                    }
                }
            }
        }
        $restaurantName = Helpers::get_business_settings('restaurant_name');
        $value = Helpers::text_variable_data_format(value:$message, user_name: $customerName, restaurant_name: $restaurantName,  order_id: $order_id);

        try {
            if ($value && isset($fcmToken)) {
                $data = [
                    'title' => translate('Order'),
                    'description' => $value,
                    'order_id' => $authenticatedUser ? $order_id : null,
                    'image' => '',
                    'type' => 'order_status',
                ];
                Helpers::send_push_notif_to_device($fcmToken, $data);
            }
        } catch (\Exception $e) {
            //
        }

        try {
            $emailServices = Helpers::get_business_settings('mail_config');
            $orderMailStatus = Helpers::get_business_settings('place_order_mail_status_user');
            if (isset($emailServices['status']) && $emailServices['status'] == 1 && $orderMailStatus == 1 && $authenticatedUser) {
                Mail::to($authenticatedUser->email)->send(new \App\Mail\OrderPlaced($order_id));
            }
        }catch (\Exception $e) {
            //
        }

        if ($or['order_status'] == 'confirmed') {
            $data = [
                'title' => translate('You have a new order - (Order Confirmed).'),
                'description' => $order_id,
                'order_id' => $order_id,
                'image' => '',
                'order_status' => $or['order_status'],
                'is_confirmation' => '0',
            ];

            try {
                Helpers::send_push_notif_to_topic(data: $data, topic: "kitchen-{$or['branch_id']}", type: 'general', isNotificationPayloadRemove: true);

            } catch (\Exception $e) {
                //
            }
        }

        // Send notification for pending orders (with sound)
        if ($or['order_status'] == 'pending') {
            $data = [
                'title' => translate('You have a new order - (Order Pending).'),
                'description' => $order_id,
                'order_id' => $order_id,
                'image' => '',
                'order_status' => $or['order_status'],
                'is_confirmation' => '0',
            ];

            try {
                Helpers::send_push_notif_to_topic(data: $data, topic: "kitchen-{$or['branch_id']}", type: 'general', isNotificationPayloadRemove: true);

            } catch (\Exception $e) {
                //
            }
        }

        try {
            $data = [
                'title' => translate('New Order Notification'),
                'description' => translate('You have new order, Check Please'),
                'order_id' => $order_id,
                'image' => '',
                'type' => 'new_order_admin',
            ];

            Helpers::send_push_notif_to_topic(data: $data, topic: 'admin_message', type: 'order_request', web_push_link: route('admin.orders.list',['status'=>'all']));
            Helpers::send_push_notif_to_topic(data: $data, topic: 'branch-order-'. $or['branch_id'] .'-message', type: 'order_request', web_push_link: route('branch.orders.list',['status'=>'all']));
        }catch (\Exception $exception){
            //
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderList(Request $request): JsonResponse
    {
        $authenticatedUser = $this->getAuthenticatedUser($request);
        $userId = $authenticatedUser ? $authenticatedUser->id : $request['guest_id'];
        $userType = $authenticatedUser ? 0 : 1;
        $orderFilter = $request->order_filter;

        $orders = $this->order->with(['customer', 'delivery_man.rating'])
            ->withCount('details')
            ->withCount(['details as total_quantity' => function($query) {
                $query->select(DB::raw('sum(quantity)'));
            }])
            ->where(['user_id' => $userId, 'is_guest' => $userType])
            ->when($orderFilter == 'history', function ($query) use ($orderFilter) {
                $query->whereIn('order_status', ['delivered', 'canceled', 'failed', 'returned']);
            })
            ->when($orderFilter == 'ongoing', function ($query) use ($orderFilter) {
                $query->whereNotIn('order_status', ['delivered', 'canceled', 'failed', 'returned']);
            })
            ->orderBy('id', 'DESC')
            ->paginate($request['limit'], ['*'], 'page', $request['offset']);


        $orders->map(function ($data) {
            $data['deliveryman_review_count'] = DMReview::where(['delivery_man_id' => $data['delivery_man_id'], 'order_id' => $data['id']])->count();

            $order_id = $data->id;
            $order_details = $this->order_detail->where('order_id', $order_id)->first();
            $product_id = $order_details?->product_id;

            $data['is_product_available'] = $product_id ? $this->product->find($product_id) ? 1 : 0 : 0;
            $data['details_count'] = (int)$data->details_count;

            $productImages = $this->order_detail->where('order_id', $order_id)->pluck('product_id')
                ->filter()
                ->map(function ($product_id) {
                    $product = $this->product->find($product_id);
                    return $product ? $product->image : null;
                })->filter();

            $data['product_images'] = $productImages->toArray();

            return $data;
        });

        $ordersArray = [
            'total_size' => $orders->total(),
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'orders' => $orders->items(),
        ];

        return response()->json($ordersArray, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderDetails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $authenticatedUser = $this->getAuthenticatedUser($request);
        $userId = $authenticatedUser ? $authenticatedUser->id : $request['guest_id'];
        $userType = $authenticatedUser ? 0 : 1;

        $details = $this->order_detail->with(['order',
            'order.delivery_man' => function ($query) {
                $query->select('id', 'f_name', 'l_name', 'phone', 'email', 'image', 'branch_id', 'is_active');
            },
            'order.delivery_man.rating', 'order.delivery_address', 'order.order_partial_payments' , 'order.offline_payment', 'order.deliveryman_review'])
            ->withCount(['reviews'])
            ->where(['order_id' => $request['order_id']])
            ->whereHas('order', function ($q) use ($userId, $userType){
                $q->where([ 'user_id' => $userId, 'is_guest' => $userType ]);
            })
            ->get();

        if ($details->count() < 1) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('Order not found!')]
                ]
            ], 404);
        }

        $details = Helpers::order_details_formatter($details);
        return response()->json($details, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function cancelOrder(Request $request): JsonResponse
    {
        $order = $this->order::find($request['order_id']);

        if (!isset($order)){
            return response()->json(['errors' => [['code' => 'order', 'message' => 'Order not found!']]], 404);
        }

        if ($order->order_status != 'pending'){
            return response()->json(['errors' => [['code' => 'order', 'message' => 'Order can only cancel when order status is pending!']]], 403);
        }

        $authenticatedUser = $this->getAuthenticatedUser($request);
        $userId = $authenticatedUser ? $authenticatedUser->id : $request['guest_id'];
        $userType = $authenticatedUser ? 0 : 1;

        if ($this->order->where(['user_id' => $userId, 'is_guest' => $userType, 'id' => $request['order_id']])->first()) {
            $this->order->where(['user_id' => $userId, 'is_guest' => $userType, 'id' => $request['order_id']])->update([
                'order_status' => 'canceled'
            ]);
            return response()->json(['message' => translate('order_canceled')], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('no_data_found')]
            ]
        ], 401);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePaymentMethod(Request $request): JsonResponse
    {
        $authenticatedUser = $this->getAuthenticatedUser($request);
        if (!$authenticatedUser) {
            return response()->json([
                'errors' => [
                    ['code' => 'auth', 'message' => translate('Authentication required')]
                ]
            ], 401);
        }
        
        if ($this->order->where(['user_id' => $authenticatedUser->id, 'id' => $request['order_id']])->first()) {
            $this->order->where(['user_id' => $authenticatedUser->id, 'id' => $request['order_id']])->update([
                'payment_method' => $request['payment_method']
            ]);
            return response()->json(['message' => translate('payment_method_updated')], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('no_data_found')]
            ]
        ], 401);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function guestTrackOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $orderId = $request->input('order_id');
        $phone = $request->input('phone');
        //dd($orderId, $phone);

        $order = $this->order->with(['customer'])
            ->where('id', $orderId)
            ->where(function ($query) use ($phone) {
                $query->where(function ($subQuery) use ($phone) {
                    $subQuery->where('is_guest', 0)
                        ->whereHas('customer', function ($customerSubQuery) use ($phone) {
                            $customerSubQuery->where('phone', $phone);
                        });
                })
                    ->orWhere(function ($subQuery) use ($phone) {
                        // Check for guest orders (both old & new formats)
                        $subQuery->where('is_guest', 1)
                            ->whereHas('delivery_address', function ($addressSubQuery) use ($phone) {
                                // Old method: Check `delivery_address` table
                                $addressSubQuery->where('contact_person_number', $phone);
                            })
                            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.delivery_address, '$.contact_person_number')) = ?", [$phone]);
                        // New method: Check JSON column in `orders` table
                    });
            })
            ->first();


        if (!isset($order)) {
            return response()->json(['errors' => [['code' => 'order', 'message' => translate('Order not found!')]]], 404);
        }

        return response()->json(OrderLogic::track_order($request['order_id']), 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getGuestOrderDetails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $phone = $request->input('phone');

        $details = $this->order_detail->with([
            'order',
            'order.customer',
            'order.order_partial_payments'
        ])
            ->withCount(['reviews'])
            ->where(['order_id' => $request['order_id']])
            ->where(function ($query) use ($phone) {
                $query->where(function ($subQuery) use ($phone) {
                    // Check for registered customers (not guests)
                    $subQuery->whereHas('order', function ($orderSubQuery) use ($phone) {
                        $orderSubQuery->where('is_guest', 0)
                            ->whereHas('customer', function ($customerSubQuery) use ($phone) {
                                $customerSubQuery->where('phone', $phone);
                            });
                    });
                })
                    ->orWhere(function ($subQuery) use ($phone) {
                        // Check for guest orders (both old & new formats)
                        $subQuery->whereHas('order', function ($orderSubQuery) use ($phone) {
                            $orderSubQuery->where('is_guest', 1)
                                ->whereHas('delivery_address', function ($addressSubQuery) use ($phone) {
                                    // Old format: Check `delivery_address` table
                                    $addressSubQuery->where('contact_person_number', $phone);
                                })
                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(orders.delivery_address, '$.contact_person_number')) = ?", [$phone]);
                            // New format: Check JSON column in `orders` table
                        });
                    });
            })
            ->get();

        if ($details->count() < 1) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('Order not found!')]
                ]
            ], 404);
        }

        $details = Helpers::order_details_formatter($details);
        return response()->json($details, 200);
    }


}


