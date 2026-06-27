@php
    use Illuminate\Support\Facades\Schema;

    $banner = $banner ?? null;
    $isEdit = $banner !== null;
    $groupOneItems = $isEdit && Schema::hasTable('banner_group_items')
        ? $banner->groupItems->where('group_number', 1)->values()
        : collect();
    $groupTwoItems = $isEdit && Schema::hasTable('banner_group_items')
        ? $banner->groupItems->where('group_number', 2)->values()
        : collect();
@endphp

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label class="input-label">{{ translate('title') }} ({{ translate('admin') }})<span class="text-danger ml-1">*</span></label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $banner?->title ?? '') }}" required>
        </div>
        <div class="form-group">
            <label class="input-label">{{ translate('headline') }}<span class="text-danger ml-1">*</span></label>
            <input type="text" name="headline" class="form-control" value="{{ old('headline', $banner?->headline ?? '') }}" required>
        </div>
        <div class="form-group">
            <label class="input-label">{{ translate('description') }}</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $banner?->description ?? '') }}</textarea>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <div class="d-flex align-items-center justify-content-center gap-1">
                <label class="mb-0">{{ translate('banner_Image') }}</label>
                <small class="text-danger">* ( {{ translate('ratio 2:1') }} )</small>
            </div>
            <div class="d-flex justify-content-center mt-4">
                <div class="upload-file cmn_focus rounded">
                    <input type="file" name="image" accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" class="upload-file__input banner-image-input" {{ $isEdit ? '' : 'required' }}>
                    <div class="upload-file__img_drag upload-file__img max-h-200px overflow-hidden">
                        <img width="465" class="banner-image-preview" src="{{ $isEdit ? $banner->imageFullPath : asset('public/assets/admin/img/icons/upload_img2.png') }}" alt="">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3">{{ translate('Eligible items') }}</h5>
<div class="row">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ translate('Items Group 1') }}</strong>
                <small class="text-muted d-block">{{ translate('Customer buys one of these') }}</small>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="input-label">{{ translate('product') }}</label>
                    <div class="input-group">
                        <select id="group-1-product-picker" class="custom-select js-select2-custom">
                            <option value="">{{ translate('select_a_product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-primary" id="group-1-add-product">{{ translate('Add') }}</button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1">{{ translate('Selected products appear in the list below') }}</small>
                </div>
                <div id="group-1-items" class="promo-group-items" data-group="1">
                    @foreach($groupOneItems as $index => $item)
                        @include('admin-views.banner.partials.group-item', ['group' => 1, 'index' => $index, 'item' => $item, 'products' => $products])
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ translate('Items Group 2') }}</strong>
                <small class="text-muted d-block">{{ translate('Customer receives the offer on one of these') }}</small>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="input-label">{{ translate('product') }}</label>
                    <div class="input-group">
                        <select id="group-2-product-picker" class="custom-select js-select2-custom">
                            <option value="">{{ translate('select_a_product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-primary" id="group-2-add-product">{{ translate('Add') }}</button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1">{{ translate('Selected products appear in the list below') }}</small>
                </div>
                <div id="group-2-items" class="promo-group-items" data-group="2">
                    @foreach($groupTwoItems as $index => $item)
                        @include('admin-views.banner.partials.group-item', ['group' => 2, 'index' => $index, 'item' => $item, 'products' => $products])
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3">{{ translate('Promotion Type') }}</h5>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label class="input-label d-block">{{ translate('type') }}</label>
            @php $promotionType = old('promotion_type', $banner?->promotion_type ?? 'bogo'); @endphp
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="promo-bogo" name="promotion_type" value="bogo" class="custom-control-input promotion-type-input" {{ $promotionType === 'bogo' ? 'checked' : '' }}>
                <label class="custom-control-label" for="promo-bogo">BOGO</label>
            </div>
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="promo-percent" name="promotion_type" value="percent_off" class="custom-control-input promotion-type-input" {{ $promotionType === 'percent_off' ? 'checked' : '' }}>
                <label class="custom-control-label" for="promo-percent">% {{ translate('Off') }}</label>
            </div>
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="promo-fixed" name="promotion_type" value="fixed_amount" class="custom-control-input promotion-type-input" {{ $promotionType === 'fixed_amount' ? 'checked' : '' }}>
                <label class="custom-control-label" for="promo-fixed">{{ translate('Fixed Amount Off') }}</label>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label class="input-label" id="reward-discount-label">{{ translate('Reward Discount') }}</label>
            <input type="number" step="0.01" min="0" name="reward_discount_value" id="reward-discount-value" class="form-control" value="{{ old('reward_discount_value', $banner?->reward_discount_value ?? 100) }}" required>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label class="input-label">{{ translate('Discount for cheapest item') }} (%)</label>
            <input type="number" step="0.01" min="0" max="100" name="discount_cheapest_percent" id="discount-cheapest-percent" class="form-control" value="{{ old('discount_cheapest_percent', $banner?->discount_cheapest_percent ?? '') }}">
        </div>
        <div class="form-group mb-0">
            <label class="input-label">{{ translate('Discount for most expensive item') }} (%)</label>
            <input type="number" step="0.01" min="0" max="100" name="discount_expensive_percent" id="discount-expensive-percent" class="form-control" value="{{ old('discount_expensive_percent', $banner?->discount_expensive_percent ?? '') }}">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="custom-control custom-checkbox mb-2">
            <input type="checkbox" class="custom-control-input" id="charge-paid-addons" name="charge_paid_addons" value="1" {{ old('charge_paid_addons', $banner?->charge_paid_addons ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="charge-paid-addons">{{ translate('Charge extra for Group 1 addons/modifiers') }}</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="custom-control custom-checkbox mb-2">
            <input type="checkbox" class="custom-control-input" id="charge-reward-addons" name="charge_reward_addons" value="1" {{ old('charge_reward_addons', $banner?->charge_reward_addons ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="charge-reward-addons">{{ translate('Charge extra for Group 2 addons/modifiers') }}</label>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3">{{ translate('Order type') }}</h5>
@php
    $orderTypeMode = old('order_type_mode', $banner?->order_type_mode ?? 'any');
    $selectedOrderTypes = old('order_types', $banner?->order_types ?? []);
@endphp
<div class="row">
    <div class="col-md-4">
        <div class="custom-control custom-radio">
            <input type="radio" id="order-type-any" name="order_type_mode" value="any" class="custom-control-input order-type-mode" {{ $orderTypeMode === 'any' ? 'checked' : '' }}>
            <label class="custom-control-label" for="order-type-any">{{ translate('Any type') }}</label>
        </div>
        <div class="custom-control custom-radio">
            <input type="radio" id="order-type-custom" name="order_type_mode" value="custom" class="custom-control-input order-type-mode" {{ $orderTypeMode === 'custom' ? 'checked' : '' }}>
            <label class="custom-control-label" for="order-type-custom">{{ translate('Custom selection') }}</label>
        </div>
    </div>
    <div class="col-md-8" id="order-type-options" style="{{ $orderTypeMode === 'custom' ? '' : 'display:none' }}">
        <div class="custom-control custom-checkbox custom-control-inline">
            <input type="checkbox" class="custom-control-input" id="order-type-takeaway" name="order_types[]" value="take_away" {{ in_array('take_away', $selectedOrderTypes ?? [], true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="order-type-takeaway">{{ translate('take_away') }}</label>
        </div>
        <div class="custom-control custom-checkbox custom-control-inline">
            <input type="checkbox" class="custom-control-input" id="order-type-delivery" name="order_types[]" value="delivery" {{ in_array('delivery', $selectedOrderTypes ?? [], true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="order-type-delivery">{{ translate('delivery') }}</label>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3">{{ translate('Limits') }}</h5>
@php $selectedPayments = old('payment_methods', $banner?->payment_methods ?? []); @endphp
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label class="input-label">{{ translate('Payment methods') }}</label>
            <select name="payment_methods[]" class="custom-select" multiple>
                @foreach($paymentMethods as $value => $label)
                    <option value="{{ $value }}" {{ in_array($value, $selectedPayments ?? [], true) ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <small class="text-muted">{{ translate('Leave empty for all payment methods') }}</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="custom-control custom-checkbox mb-3">
            <input type="checkbox" class="custom-control-input" id="once-per-customer" name="once_per_customer" value="1" {{ old('once_per_customer', $banner?->once_per_customer ?? false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="once-per-customer">{{ translate('Only once per client') }}</label>
        </div>
        <div class="form-group">
            <label class="input-label">{{ translate('Customer eligibility') }}</label>
            @php $customerEligibility = old('customer_eligibility', $banner?->customer_eligibility ?? 'any'); @endphp
            <select name="customer_eligibility" class="custom-select">
                <option value="any" {{ $customerEligibility === 'any' ? 'selected' : '' }}>{{ translate('Any customer') }}</option>
                <option value="new" {{ $customerEligibility === 'new' ? 'selected' : '' }}>{{ translate('New customers only') }}</option>
                <option value="returned" {{ $customerEligibility === 'returned' ? 'selected' : '' }}>{{ translate('Returning customers only') }}</option>
            </select>
        </div>
        <div class="form-group">
            <label class="input-label">{{ translate('Maximum reward quantity') }}</label>
            <input type="number" min="1" name="max_reward_qty" class="form-control" value="{{ old('max_reward_qty', $banner?->max_reward_qty ?? 1) }}" required>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label class="input-label">{{ translate('Usage per customer') }}</label>
            <input type="number" min="1" name="usage_per_customer" class="form-control" value="{{ old('usage_per_customer', $banner?->usage_per_customer ?? '') }}">
        </div>
        <div class="form-group">
            <label class="input-label">{{ translate('Total usage') }}</label>
            <input type="number" min="1" name="total_usage_limit" class="form-control" value="{{ old('total_usage_limit', $banner?->total_usage_limit ?? '') }}">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label class="input-label">{{ translate('start') }} {{ translate('date') }}</label>
            <input type="datetime-local" name="start_date" class="form-control" value="{{ old('start_date', $banner?->start_date?->format('Y-m-d\TH:i') ?? '') }}">
        </div>
        <div class="form-group">
            <label class="input-label">{{ translate('end') }} {{ translate('date') }}</label>
            <input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date', $banner?->end_date?->format('Y-m-d\TH:i') ?? '') }}">
        </div>
    </div>
</div>
