<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\AddOn;
use App\Model\Branch;
use App\Model\Category;
use App\Model\Order;
use App\Model\Product;
use App\Model\ProductByBranch;
use App\Model\Table;
use App\Models\DeliveryChargeByArea;
use App\Services\Pos\PosCartService;
use App\Services\Pos\PosOrderService;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PosController extends Controller
{
    public function __construct(
        private PosCartService $cartService,
        private PosOrderService $orderService,
        private Category $category,
        private Product $product,
        private ProductByBranch $productByBranch,
        private Branch $branch,
        private Table $table,
        private User $user,
        private Order $order,
    ) {
    }

    private function adminId(): int
    {
        return (int) auth('admin_api')->id();
    }

    public function bootstrap(): JsonResponse
    {
        $logoName = Helpers::get_business_settings('logo');
        $logo = Helpers::onErrorImage(
            $logoName,
            asset('storage/app/public/restaurant') . '/' . $logoName,
            asset('public/assets/admin/img/logo.png'),
            'restaurant/'
        );

        $currency = DB::table('currencies')
            ->where('currency_code', Helpers::currency_code())
            ->first();

        return response()->json([
            'restaurant_name' => Helpers::get_business_settings('restaurant_name'),
            'logo' => $logo,
            'currency_symbol' => $currency->currency_symbol ?? '$',
            'currency_code' => Helpers::currency_code(),
            'branches' => $this->branch->select('id', 'name', 'email', 'phone')->get(),
            'session' => $this->cartService->getSession($this->adminId()),
            'payment_methods' => ['cash', 'card', 'pay_after_eating'],
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = $this->category->where(['position' => 0])->active()->orderBy('priority', 'ASC')->get(['id', 'name', 'image']);
        return response()->json(['categories' => $categories]);
    }

    public function products(Request $request): JsonResponse
    {
        $adminId = $this->adminId();
        $branchId = $request->query('branch_id') ?? $this->cartService->branchId($adminId);
        $categoryId = (int) $request->query('category_id', 0);
        $keyword = $request->query('keyword', '');
        $limit = (int) $request->query('limit', 20);
        $offset = (int) $request->query('offset', 1);
        $key = explode(' ', $keyword);

        $query = $this->product
            ->with(['branch_products' => function ($q) use ($branchId) {
                $q->where(['is_available' => 1, 'branch_id' => $branchId]);
            }])
            ->whereHas('branch_products', function ($q) use ($branchId) {
                $q->where(['is_available' => 1, 'branch_id' => $branchId]);
            })
            ->when($categoryId > 0, function ($query) use ($categoryId) {
                $query->whereJsonContains('category_ids', [['id' => (string) $categoryId]]);
            })
            ->when($keyword, function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->active()
            ->latest();

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);
        $products = Helpers::product_data_formatting($paginator->items(), true);
        $products = array_map(function ($item) {
            $item['image_url'] = $this->productImageUrl($item['image'] ?? null);

            return $item;
        }, $products);

        return response()->json([
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $products,
        ]);
    }

    public function productDetails(int $id): JsonResponse
    {
        $branchId = $this->cartService->branchId($this->adminId());
        $product = $this->product->with(['branch_products' => function ($q) use ($branchId) {
            $q->where(['branch_id' => $branchId, 'is_available' => 1]);
        }])->active()->findOrFail($id);

        $formatted = Helpers::product_data_formatting($product, false);
        $formatted['image_url'] = $this->productImageUrl($formatted['image'] ?? $product->image);
        $branchProduct = $this->productByBranch->where(['product_id' => $id, 'branch_id' => $branchId])->first();

        $availableStock = null;
        $stockType = 'unlimited';
        if ($branchProduct && in_array($branchProduct->stock_type, ['daily', 'fixed'], true)) {
            $stockType = 'limited';
            $availableStock = $branchProduct->stock - $branchProduct->sold_quantity;
        }

        return response()->json([
            'product' => $formatted,
            'branch_product' => $branchProduct,
            'stock_type' => $stockType,
            'available_stock' => $availableStock,
        ]);
    }

    public function cart(): JsonResponse
    {
        return response()->json($this->cartService->buildCartResponse($this->adminId()));
    }

    public function addToCart(Request $request): JsonResponse
    {
        $result = $this->cartService->addToCart($this->adminId(), $request->all());
        if ($result['error'] ?? false) {
            return response()->json([
                'data' => $result['code'] ?? 'error',
                'message' => $result['message'],
            ], 422);
        }
        return response()->json([
            'data' => $result['item'],
            'key' => $result['key'],
            'cart' => $this->cartService->buildCartResponse($this->adminId()),
        ]);
    }

    public function updateCartItem(Request $request, int $key): JsonResponse
    {
        $updated = $this->cartService->updateQuantity($this->adminId(), $key, (int) $request->quantity);
        if (!$updated) {
            return response()->json(['errors' => [['message' => translate('Item not found')]]], 404);
        }
        return response()->json($this->cartService->buildCartResponse($this->adminId()));
    }

    public function removeCartItem(int $key): JsonResponse
    {
        $this->cartService->removeItem($this->adminId(), $key);
        return response()->json($this->cartService->buildCartResponse($this->adminId()));
    }

    public function emptyCart(): JsonResponse
    {
        $this->cartService->emptyCart($this->adminId());
        return response()->json($this->cartService->buildCartResponse($this->adminId()));
    }

    public function updateTax(Request $request): JsonResponse
    {
        $tax = (float) $request->tax;
        if ($tax < 0 || $tax > 100) {
            return response()->json(['errors' => [['message' => translate('Tax_can_not_be_more_than_100_percent')]]], 422);
        }
        $this->cartService->updateTax($this->adminId(), $tax);
        return response()->json($this->cartService->buildCartResponse($this->adminId()));
    }

    public function updateDiscount(Request $request): JsonResponse
    {
        $result = $this->cartService->updateDiscount(
            $this->adminId(),
            $request->type ?? 'amount',
            (float) ($request->discount ?? 0)
        );
        if ($result['error'] ?? false) {
            return response()->json(['errors' => [['message' => $result['message']]]], 422);
        }
        return response()->json($this->cartService->buildCartResponse($this->adminId()));
    }

    public function variantPrice(Request $request): JsonResponse
    {
        return response()->json($this->cartService->variantPrice($this->adminId(), $request->all()));
    }

    public function updateSession(Request $request): JsonResponse
    {
        $session = $this->cartService->updateSession($this->adminId(), $request->only([
            'branch_id', 'customer_id', 'order_type', 'table_id', 'people_number', 'address',
        ]));
        return response()->json([
            'session' => $session,
            'cart' => $this->cartService->buildCartResponse($this->adminId()),
        ]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $name = trim($request->name);
        if ($name === '') {
            return response()->json(['errors' => [['message' => translate('name is required')]]], 422);
        }

        $parts = preg_split('/\s+/', $name, 2);
        $fName = $parts[0];
        $lName = $parts[1] ?? '';

        $phone = $request->filled('phone') ? trim($request->phone) : null;
        $email = $request->filled('email') ? trim($request->email) : null;

        if ($phone && $this->user->where('phone', $phone)->exists()) {
            return response()->json(['errors' => [['message' => translate('The phone is already taken')]]], 422);
        }

        if ($email && $this->user->where('email', $email)->exists()) {
            return response()->json(['errors' => [['message' => translate('The email is already taken')]]], 422);
        }

        $customer = $this->user->create([
            'f_name' => $fName,
            'l_name' => $lName,
            'email' => $email,
            'phone' => $phone,
            'password' => bcrypt('password'),
        ]);

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'text' => $this->formatCustomerLabel($customer),
            ],
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $q = $request->query('q', '');
        $key = explode(' ', $q);
        $data = $this->user
            ->where(['user_type' => null])
            ->whereNotNull('f_name')
            ->when($q, function ($query) use ($key) {
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        if ($value === '') {
                            continue;
                        }
                        $q->orWhere('f_name', 'like', "%{$value}%")
                            ->orWhere('l_name', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%");
                    }
                });
            })
            ->limit(20)
            ->get(['id', 'f_name', 'l_name', 'phone', 'email']);

        $customers = $data->map(fn ($c) => [
            'id' => $c->id,
            'text' => $this->formatCustomerLabel($c),
        ])->values();

        return response()->json([
            'customers' => $customers->prepend(['id' => null, 'text' => translate('walk_in_customer')])->values(),
        ]);
    }

    private function formatCustomerLabel($customer): string
    {
        $label = trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? ''));
        if (!empty($customer->phone)) {
            $label .= ' (' . $customer->phone . ')';
        }

        return $label;
    }

    public function tables(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id') ?? $this->cartService->branchId($this->adminId());
        $tables = $this->table->where(['is_active' => 1, 'branch_id' => $branchId])->get(['id', 'number', 'capacity']);
        return response()->json(['tables' => $tables]);
    }

    public function deliveryInfo(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id') ?? $this->cartService->branchId($this->adminId());
        $branch = $this->branch->with(['delivery_charge_setup', 'delivery_charge_by_area'])->find($branchId);
        if (!$branch) {
            return response()->json(['errors' => [['message' => translate('Branch not found')]]], 404);
        }

        $deliveryType = $branch->delivery_charge_setup?->delivery_charge_type ?? 'fixed';
        $areas = DeliveryChargeByArea::where('branch_id', $branchId)->get(['id', 'area_name', 'delivery_charge']);

        return response()->json([
            'delivery_type' => $deliveryType,
            'areas' => $areas,
            'branch' => [
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
            ],
        ]);
    }

    public function addDeliveryAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_person_name' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $branchId = $this->cartService->branchId($this->adminId());
        $branch = $this->branch->find($branchId);
        $distance = 0;
        $area = null;

        if ($request->has('latitude') && $request->has('longitude') && $branch) {
            $data = $this->computeDistance(
                $branch->latitude,
                $branch->longitude,
                $request->latitude,
                $request->longitude
            );
            if (!empty($data[0]['distanceMeters'])) {
                $distance = $data[0]['distanceMeters'] / 1000;
            }
        }

        if ($request->selected_area_id) {
            $area = DeliveryChargeByArea::find($request->selected_area_id);
        }

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => 'Home',
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'distance' => $distance,
            'longitude' => (string) ($request->longitude ?? ''),
            'latitude' => (string) ($request->latitude ?? ''),
            'area_id' => $request->selected_area_id,
            'area_name' => $area->area_name ?? null,
        ];

        $this->cartService->updateSession($this->adminId(), ['address' => $address]);

        return response()->json([
            'address' => $address,
            'cart' => $this->cartService->buildCartResponse($this->adminId()),
        ]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $result = $this->orderService->placeOrder($this->adminId(), $request->all());
        if ($result['error'] ?? false) {
            return response()->json(['errors' => [['message' => $result['message']]]], 422);
        }
        return response()->json([
            'order_id' => $result['order_id'],
            'message' => $result['message'],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $branchId = $request->query('branch_id');
        $from = $request->query('from');
        $to = $request->query('to');
        $limit = (int) $request->query('limit', Helpers::getPagination());
        $offset = (int) $request->query('offset', 1);

        $query = $this->posOrdersBaseQuery()
            ->with(['customer:id,f_name,l_name,phone', 'branch:id,name']);

        if ($request->filled('search')) {
            $key = explode(' ', $search);
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    if ($value === '') {
                        continue;
                    }
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('transaction_reference', 'like', "%{$value}%");
                }
            });
        } elseif ($request->has('filter')) {
            $query->when($from && $to && $branchId === 'all', function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, Carbon::parse($to)->endOfDay()]);
            })
                ->when($from && $to && $branchId !== 'all' && $branchId !== null, function ($q) use ($from, $to, $branchId) {
                    $q->whereBetween('created_at', [$from, Carbon::parse($to)->endOfDay()])
                        ->where('branch_id', $branchId);
                })
                ->when(!$from && !$to && $branchId !== 'all' && $branchId !== null, function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
        }

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $offset);

        return response()->json([
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'orders' => collect($paginator->items())->map(fn ($order) => $this->formatOrderSummary($order))->values(),
        ]);
    }

    public function orderDetails(int $id): JsonResponse
    {
        $order = $this->findPosOrder($id);
        if (!$order) {
            return response()->json(['errors' => [['message' => translate('Order not found')]]], 404);
        }

        return response()->json(['order' => $this->formatOrderDetail($order)]);
    }

    public function orderInvoice(int $id): JsonResponse
    {
        $order = $this->findPosOrder($id);
        if (!$order) {
            return response()->json(['errors' => [['message' => translate('Order not found')]]], 404);
        }

        $order->load(['details.product', 'customer', 'order_change_amount']);

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.pos.order.invoice', compact('order'))->render(),
        ]);
    }

    private function posOrdersBaseQuery()
    {
        return $this->order->newQuery()
            ->whereIn('order_type', ['pos', 'dine_in', 'delivery'])
            ->where('checked', 1);
    }

    private function findPosOrder(int $id): ?Order
    {
        return $this->posOrdersBaseQuery()
            ->with([
                'customer:id,f_name,l_name,phone,email',
                'branch:id,name,phone,address',
                'table:id,number,capacity',
                'customer_delivery_address',
                'details.product',
                'order_change_amount',
            ])
            ->where('id', $id)
            ->first();
    }

    private function formatOrderSummary(Order $order): array
    {
        $customerInfo = $this->orderCustomerInfo($order);

        return [
            'id' => $order->id,
            'created_at' => $order->created_at?->toIso8601String(),
            'order_amount' => (float) $order->order_amount,
            'payment_status' => $order->payment_status,
            'order_status' => $order->order_status,
            'order_type' => $order->order_type,
            'payment_method' => $order->payment_method,
            'customer' => $customerInfo['customer'],
            'customer_label' => $customerInfo['label'],
            'branch' => $order->branch ? [
                'id' => $order->branch->id,
                'name' => $order->branch->name,
            ] : null,
        ];
    }

    private function orderCustomerInfo(Order $order): array
    {
        if ($order->user_id === null) {
            return [
                'label' => 'walk_in',
                'customer' => null,
            ];
        }

        if (!$order->customer) {
            return [
                'label' => 'unavailable',
                'customer' => null,
            ];
        }

        return [
            'label' => null,
            'customer' => [
                'name' => trim(($order->customer->f_name ?? '') . ' ' . ($order->customer->l_name ?? '')),
                'phone' => $order->customer->phone,
            ],
        ];
    }

    private function formatOrderDetail(Order $order): array
    {
        $customerInfo = $this->orderCustomerInfo($order);
        $items = [];
        $itemPrice = 0;
        $totalTax = 0;
        $addOnsCost = 0;
        $addOnsTaxCost = 0;

        foreach ($order->details as $detail) {
            if (!$detail->product) {
                continue;
            }

            $formatted = $this->formatOrderDetailLine($detail);
            $items[] = $formatted;
            $lineAmount = ($detail->price - $detail->discount_on_product) * $detail->quantity;
            $itemPrice += $lineAmount;
            $totalTax += $detail->tax_amount * $detail->quantity;
            $addOnsCost += $formatted['addon_cost'];
            $addOnsTaxCost += $formatted['addon_tax_cost'];
        }

        $couponDiscount = (float) ($order->coupon_discount_amount ?? 0);
        $extraDiscount = (float) ($order->extra_discount ?? 0);
        $deliveryCharge = $order->order_type === 'pos' ? 0.0 : (float) ($order->delivery_charge ?? 0);
        $taxTotal = $totalTax + $addOnsTaxCost;
        $subtotal = $addOnsCost + $itemPrice + $taxTotal - $couponDiscount - $extraDiscount;

        $changeAmount = null;
        $paidAmount = null;
        if ($order->order_change_amount) {
            $paidAmount = (float) $order->order_change_amount->paid_amount;
            $changeAmount = $paidAmount - (float) $order->order_change_amount->order_amount;
        }

        $deliveryAddress = null;
        if ($order->order_type === 'delivery' && $order->customer_delivery_address) {
            $addr = $order->customer_delivery_address;
            $deliveryAddress = [
                'contact_person_name' => $addr->contact_person_name ?? null,
                'contact_person_number' => $addr->contact_person_number ?? null,
                'address' => $addr->address ?? null,
            ];
        }

        return [
            'id' => $order->id,
            'created_at' => $order->created_at?->toIso8601String(),
            'order_type' => $order->order_type,
            'order_status' => $order->order_status,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'order_note' => $order->order_note,
            'transaction_reference' => $order->transaction_reference,
            'branch' => $order->branch ? [
                'id' => $order->branch->id,
                'name' => $order->branch->name,
            ] : null,
            'customer' => $customerInfo['customer'],
            'customer_label' => $customerInfo['label'],
            'table' => $order->table ? [
                'id' => $order->table->id,
                'number' => $order->table->number,
            ] : null,
            'number_of_people' => $order->number_of_people,
            'delivery_address' => $deliveryAddress,
            'items' => $items,
            'summary' => [
                'items_price' => round($itemPrice, 2),
                'addon_cost' => round($addOnsCost, 2),
                'coupon_discount' => round($couponDiscount, 2),
                'extra_discount' => round($extraDiscount, 2),
                'tax' => round($taxTotal, 2),
                'subtotal' => round($subtotal, 2),
                'delivery_charge' => round($deliveryCharge, 2),
                'total' => round((float) $order->order_amount, 2),
                'paid_amount' => $paidAmount,
                'change_or_due_amount' => $changeAmount,
            ],
        ];
    }

    private function formatOrderDetailLine($detail): array
    {
        $variations = [];
        $variationData = json_decode($detail->variation ?? '[]', true) ?: [];
        if (is_array($variationData)) {
            foreach ($variationData as $variation) {
                if (isset($variation['name'], $variation['values']) && is_array($variation['values'])) {
                    $labels = [];
                    foreach ($variation['values'] as $value) {
                        $labels[] = ($value['label'] ?? '') . ': ' . Helpers::set_symbol($value['optionPrice'] ?? 0);
                    }
                    $variations[] = [
                        'name' => $variation['name'],
                        'values' => $labels,
                    ];
                } elseif (is_array($variation)) {
                    foreach ($variation as $key => $val) {
                        $variations[] = ['name' => (string) $key, 'values' => [(string) $val]];
                    }
                }
            }
        }

        $addons = [];
        $addonCost = 0.0;
        $addonTaxCost = 0.0;
        $addOnIds = json_decode($detail->add_on_ids ?? '[]', true) ?: [];
        $addOnQtys = json_decode($detail->add_on_qtys ?? '[]', true);
        $addOnPrices = json_decode($detail->add_on_prices ?? '[]', true) ?: [];
        $addOnTaxes = json_decode($detail->add_on_taxes ?? '[]', true) ?: [];

        foreach ($addOnIds as $key2 => $addonId) {
            $addon = AddOn::find($addonId);
            $qty = $addOnQtys === null ? 1 : ($addOnQtys[$key2] ?? 1);
            $price = (float) ($addOnPrices[$key2] ?? 0);
            $tax = (float) ($addOnTaxes[$key2] ?? 0);
            $addonCost += $price * $qty;
            $addonTaxCost += $tax * $qty;
            $addons[] = [
                'name' => $addon ? $addon->name : translate('addon deleted'),
                'quantity' => (int) $qty,
                'price' => $price,
            ];
        }

        $lineDiscount = (float) $detail->discount_on_product * (int) $detail->quantity;
        $lineTotal = ($detail->price - $detail->discount_on_product) * $detail->quantity;

        return [
            'product_name' => $detail->product?->name,
            'quantity' => (int) $detail->quantity,
            'unit_price' => (float) $detail->price,
            'discount_on_product' => $lineDiscount,
            'tax_amount' => (float) $detail->tax_amount * (int) $detail->quantity,
            'line_total' => round($lineTotal, 2),
            'variations' => $variations,
            'addons' => $addons,
            'addon_cost' => $addonCost,
            'addon_tax_cost' => $addonTaxCost,
        ];
    }

    private function productImageUrl(?string $image): string
    {
        if (empty($image)) {
            return asset('public/assets/admin/img/160x160/img2.jpg');
        }

        return asset('storage/app/public/product/' . $image);
    }

    private function computeDistance($originLat, $originLng, $destLat, $destLng): array
    {
        $apiKey = Helpers::get_business_settings('map_api_server_key');
        $url = 'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix';

        $data = [
            'origins' => [
                'waypoint' => [
                    'location' => [
                        'latLng' => ['latitude' => $originLat, 'longitude' => $originLng],
                    ],
                ],
            ],
            'destinations' => [
                'waypoint' => [
                    'location' => [
                        'latLng' => ['latitude' => $destLat, 'longitude' => $destLng],
                    ],
                ],
            ],
            'travelMode' => 'DRIVE',
            'routingPreference' => 'TRAFFIC_AWARE',
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $apiKey,
            'X-Goog-FieldMask' => '*',
        ])->post($url, $data);

        return $response->json() ?? [];
    }
}
