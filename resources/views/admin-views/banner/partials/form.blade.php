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

<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ translate('Banner details') }}</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="form-group">
                    <label class="input-label">{{ translate('title') }} ({{ translate('admin') }})<span class="text-danger ml-1">*</span></label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $banner?->title ?? '') }}" required>
                </div>
                <div class="form-group">
                    <label class="input-label">{{ translate('headline') }}<span class="text-danger ml-1">*</span></label>
                    <input type="text" name="headline" class="form-control" value="{{ old('headline', $banner?->headline ?? '') }}" required>
                </div>
                <div class="form-group mb-0">
                    <label class="input-label">{{ translate('description') }}</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $banner?->description ?? '') }}</textarea>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group mb-0">
                    <label class="input-label d-block">{{ translate('banner_Image') }}<span class="text-danger ml-1">*</span> <small class="text-muted">({{ translate('ratio 2:1') }})</small></label>
                    <div class="d-flex justify-content-center mt-2">
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
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ translate('Eligible items') }}</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="border rounded p-3 h-100">
                    <strong class="d-block">{{ translate('Items Group 1') }}</strong>
                    <small id="group-1-help" class="text-muted d-block mb-3">{{ translate('Customer buys one of these') }}</small>
                    <button type="button" class="btn btn-primary open-product-picker mb-3" data-group="1">
                        <i class="tio-add"></i> {{ translate('Browse products') }}
                    </button>
                    <small class="text-muted d-block mb-3">{{ translate('Selected products appear in the list below') }}</small>
                    <div id="group-1-items" class="promo-group-items" data-group="1">
                        @foreach($groupOneItems as $index => $item)
                            @include('admin-views.banner.partials.group-item', ['group' => 1, 'index' => $index, 'item' => $item, 'products' => $products])
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-lg-6" id="promo-group-2-card">
                <div class="border rounded p-3 h-100">
                    <strong class="d-block">{{ translate('Items Group 2') }}</strong>
                    <small id="group-2-help" class="text-muted d-block mb-3">{{ translate('Customer receives the offer on one of these') }}</small>
                    <button type="button" class="btn btn-primary open-product-picker mb-3" data-group="2">
                        <i class="tio-add"></i> {{ translate('Browse products') }}
                    </button>
                    <small class="text-muted d-block mb-3">{{ translate('Selected products appear in the list below') }}</small>
                    <div id="group-2-items" class="promo-group-items" data-group="2">
                        @foreach($groupTwoItems as $index => $item)
                            @include('admin-views.banner.partials.group-item', ['group' => 2, 'index' => $index, 'item' => $item, 'products' => $products])
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin-views.banner.partials.product-picker-modal')

@php
    $promotionType = old('promotion_type', $banner?->promotion_type ?? 'bogo');
    $orderTypeMode = old('order_type_mode', $banner?->order_type_mode ?? 'any');
    $selectedOrderTypes = old('order_types', $banner?->order_types ?? []);
    $selectedPayments = old('payment_methods', $banner?->payment_methods ?? []);
    $customerEligibility = old('customer_eligibility', $banner?->customer_eligibility ?? 'any');
@endphp

<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ translate('Promotion settings') }}</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6 col-lg-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="promotion-type-select">{{ translate('type') }}<span class="text-danger ml-1">*</span></label>
                    <select name="promotion_type" id="promotion-type-select" class="form-control js-select2-custom promotion-type-input" required>
                        <option value="bogo" {{ $promotionType === 'bogo' ? 'selected' : '' }}>BOGO</option>
                        <option value="percent_off" {{ $promotionType === 'percent_off' ? 'selected' : '' }}>% {{ translate('Off') }}</option>
                        <option value="fixed_amount" {{ $promotionType === 'fixed_amount' ? 'selected' : '' }}>{{ translate('Fixed Amount Off') }}</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="reward-discount-value" id="reward-discount-label">{{ translate('Reward Discount') }}</label>
                    <input type="number" step="0.01" min="0" name="reward_discount_value" id="reward-discount-value" class="form-control" value="{{ old('reward_discount_value', $banner?->reward_discount_value ?? 100) }}" required>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" id="minimum-spend-field" @if($promotionType !== 'fixed_amount') style="display: none;" @endif>
                <div class="form-group mb-0">
                    <label class="input-label" for="minimum-spend-value">{{ translate('Minimum Spend') }}<span class="text-danger ml-1 minimum-spend-required">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="minimum_spend" id="minimum-spend-value" class="form-control" value="{{ old('minimum_spend', $banner?->minimum_spend) }}" @if($promotionType !== 'fixed_amount') disabled @else required @endif>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="charge-paid-addons" name="charge_paid_addons" value="1" {{ old('charge_paid_addons', $banner?->charge_paid_addons ?? true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="charge-paid-addons">{{ translate('Charge extra for Group 1 addons/modifiers') }}</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="charge-reward-addons" name="charge_reward_addons" value="1" {{ old('charge_reward_addons', $banner?->charge_reward_addons ?? false) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="charge-reward-addons">{{ translate('Charge extra for Group 2 addons/modifiers') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ translate('Order type') }}</h5>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-6 col-lg-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="order-type-mode">{{ translate('Order type') }}</label>
                    <select name="order_type_mode" id="order-type-mode" class="form-control js-select2-custom order-type-mode">
                        <option value="any" {{ $orderTypeMode === 'any' ? 'selected' : '' }}>{{ translate('Any type') }}</option>
                        <option value="custom" {{ $orderTypeMode === 'custom' ? 'selected' : '' }}>{{ translate('Custom selection') }}</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6 col-lg-8" id="order-type-options" @class(['d-none' => $orderTypeMode !== 'custom'])>
                <label class="input-label d-block">{{ translate('Allowed order types') }}</label>
                <div class="d-flex flex-wrap gap-3 pt-1">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="order-type-takeaway" name="order_types[]" value="take_away" {{ in_array('take_away', $selectedOrderTypes ?? [], true) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="order-type-takeaway">{{ translate('take_away') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="order-type-delivery" name="order_types[]" value="delivery" {{ in_array('delivery', $selectedOrderTypes ?? [], true) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="order-type-delivery">{{ translate('delivery') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-0">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ translate('Limits') }} & {{ translate('schedule') }}</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6 col-lg-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="payment-methods-select">{{ translate('Payment methods') }}</label>
                    <select name="payment_methods[]" id="payment-methods-select" class="form-control js-select2-custom" multiple data-placeholder="{{ translate('All payment methods') }}">
                        @foreach($paymentMethods as $value => $label)
                            <option value="{{ $value }}" {{ in_array($value, $selectedPayments ?? [], true) ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">{{ translate('Leave empty for all payment methods') }}</small>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="customer-eligibility">{{ translate('Customer eligibility') }}</label>
                    <select name="customer_eligibility" id="customer-eligibility" class="form-control js-select2-custom">
                        <option value="any" {{ $customerEligibility === 'any' ? 'selected' : '' }}>{{ translate('Any customer') }}</option>
                        <option value="new" {{ $customerEligibility === 'new' ? 'selected' : '' }}>{{ translate('New customers only') }}</option>
                        <option value="returned" {{ $customerEligibility === 'returned' ? 'selected' : '' }}>{{ translate('Returning customers only') }}</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="usage-per-customer">{{ translate('Usage per customer') }}</label>
                    <input type="number" min="1" name="usage_per_customer" id="usage-per-customer" class="form-control" value="{{ old('usage_per_customer', $banner?->usage_per_customer ?? '') }}" placeholder="{{ translate('Unlimited') }}">
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="total-usage-limit">{{ translate('Total usage') }}</label>
                    <input type="number" min="1" name="total_usage_limit" id="total-usage-limit" class="form-control" value="{{ old('total_usage_limit', $banner?->total_usage_limit ?? '') }}" placeholder="{{ translate('Unlimited') }}">
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="custom-control custom-checkbox mt-lg-4 pt-lg-2">
                    <input type="checkbox" class="custom-control-input" id="once-per-customer" name="once_per_customer" value="1" {{ old('once_per_customer', $banner?->once_per_customer ?? false) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="once-per-customer">{{ translate('Only once per client') }}</label>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="start-date">{{ translate('start') }} {{ translate('date') }}</label>
                    <input type="datetime-local" name="start_date" id="start-date" class="form-control" value="{{ old('start_date', $banner?->start_date?->format('Y-m-d\TH:i') ?? '') }}">
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="end-date">{{ translate('end') }} {{ translate('date') }}</label>
                    <input type="datetime-local" name="end_date" id="end-date" class="form-control" value="{{ old('end_date', $banner?->end_date?->format('Y-m-d\TH:i') ?? '') }}">
                </div>
            </div>
        </div>
    </div>
</div>
