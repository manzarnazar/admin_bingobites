<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Model\Category;
use App\Model\Product;
use App\Model\ProductByBranch;
use App\Model\Table;
use App\Models\DeliveryChargeByArea;
use App\Services\Pos\PosCartService;
use App\Services\Pos\PosOrderService;
use App\User;
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

    public function customers(Request $request): JsonResponse
    {
        $q = $request->query('q', '');
        $key = explode(' ', $q);
        $data = $this->user
            ->where(['user_type' => null])
            ->when($q, function ($query) use ($key) {
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('f_name', 'like', "%{$value}%")
                            ->orWhere('l_name', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%");
                    }
                });
            })
            ->whereNotNull(['f_name', 'l_name', 'phone'])
            ->limit(20)
            ->get(['id', 'f_name', 'l_name', 'phone']);

        $customers = $data->map(fn ($c) => [
            'id' => $c->id,
            'text' => trim($c->f_name . ' ' . $c->l_name . ' (' . $c->phone . ')'),
        ])->values();

        return response()->json([
            'customers' => $customers->prepend(['id' => null, 'text' => translate('walk_in_customer')])->values(),
        ]);
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
