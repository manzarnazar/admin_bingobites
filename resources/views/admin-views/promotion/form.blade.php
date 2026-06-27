@php
    $formAction = $promotion->id ? route('admin.promotion.update', $promotion->id) : route('admin.promotion.store');
    $group1Products = $group1Products ?? [];
    $group2Products = $group2Products ?? [];
@endphp
@extends('layouts.admin.app')

@section('title', $promotion->id ? translate('Update promotion') : translate('Add new promotion'))

@push('css_or_js')
    <link href="{{asset('public/assets/admin/css/tags-input.min.css')}}" rel="stylesheet">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/campaign.png')}}" alt="">
                <span class="page-header-title">
                    {{ $promotion->id ? translate('Update promotion') : translate('Add_New_Promotion') }}
                </span>
            </h2>
        </div>

        <form action="{{ $formAction }}" method="post" enctype="multipart/form-data">
            @csrf
            @if($promotion->id) @method('put') @endif

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{translate('Basic_Info')}}</h5></div>
                <div class="card-body row">
                    <div class="col-md-6 form-group">
                        <label class="input-label">{{translate('title')}} *</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title', $promotion->title) }}" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="input-label">{{translate('headline')}}</label>
                        <input type="text" name="headline" class="form-control" value="{{ old('headline', $promotion->headline) }}" placeholder="Buy 1 Burger, Get 1 Free">
                    </div>
                    <div class="col-md-12 form-group">
                        <label class="input-label">{{translate('description')}}</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $promotion->description) }}</textarea>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{translate('Promotion_Type')}} *</label>
                        <select name="type" class="custom-select" required>
                            <option value="bogo" {{ old('type', $promotion->type) === 'bogo' ? 'selected' : '' }}>Buy One Get One Free</option>
                            <option value="buy_get_discount" {{ old('type', $promotion->type) === 'buy_get_discount' ? 'selected' : '' }}>Buy One Get Discount on Other</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{translate('Promotion_Image')}}</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-4 form-group d-flex align-items-end">
                        <label class="toggle-switch">
                            <input type="checkbox" name="status" class="toggle-switch-input" value="1" {{ old('status', $promotion->status ?? 1) ? 'checked' : '' }}>
                            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span><span class="toggle-switch-content">{{translate('Active')}}</span></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{translate('Item_Groups')}}</h5></div>
                <div class="card-body row">
                    <div class="col-md-6 form-group">
                        <label class="input-label">{{translate('Item Group 1')}} ({{translate('paid item')}}) *</label>
                        <input type="text" name="group_1_label" class="form-control mb-2" value="{{ old('group_1_label', 'Item Group 1') }}">
                        <select name="group_1_products[]" class="form-control js-select2-custom" multiple required>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" {{ in_array($product->id, old('group_1_products', $group1Products)) ? 'selected' : '' }}>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="input-label">{{translate('Item Group 2')}} ({{translate('reward item')}})</label>
                        <input type="text" name="group_2_label" class="form-control mb-2" value="{{ old('group_2_label', 'Item Group 2') }}">
                        <select name="group_2_products[]" class="form-control js-select2-custom" multiple>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" {{ in_array($product->id, old('group_2_products', $group2Products)) ? 'selected' : '' }}>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{translate('Discount_Rules')}}</h5></div>
                <div class="card-body row">
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{translate('Discount for cheapest item')}} (%)</label>
                        <input type="number" name="discount_cheapest_percent" class="form-control" min="0" max="100" value="{{ old('discount_cheapest_percent', $promotion->discount_cheapest_percent ?? 0) }}" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{translate('Discount for most expensive item')}} (%)</label>
                        <input type="number" name="discount_expensive_percent" class="form-control" min="0" max="100" value="{{ old('discount_expensive_percent', $promotion->discount_expensive_percent ?? 100) }}" required>
                    </div>
                    <div class="col-md-4 form-group d-flex align-items-end">
                        <label class="toggle-switch">
                            <input type="checkbox" name="charge_modifier_addons" class="toggle-switch-input" value="1" {{ old('charge_modifier_addons', $promotion->charge_modifier_addons ?? true) ? 'checked' : '' }}>
                            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span><span class="toggle-switch-content">{{translate('Charge extra for modifier addons')}}</span></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{translate('Eligibility')}}</h5></div>
                <div class="card-body row">
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{translate('Client type')}}</label>
                        <select name="customer_type" class="custom-select">
                            <option value="any" {{ old('customer_type', $promotion->customer_type ?? 'any') === 'any' ? 'selected' : '' }}>Any client</option>
                            <option value="new" {{ old('customer_type', $promotion->customer_type) === 'new' ? 'selected' : '' }}>Only new clients</option>
                            <option value="returning" {{ old('customer_type', $promotion->customer_type) === 'returning' ? 'selected' : '' }}>Only returning clients</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{translate('Order type')}}</label>
                        <select name="order_type" class="custom-select">
                            <option value="any" {{ old('order_type', $promotion->order_type ?? 'any') === 'any' ? 'selected' : '' }}>Any type</option>
                            <option value="take_away" {{ old('order_type', $promotion->order_type) === 'take_away' ? 'selected' : '' }}>Pick-up</option>
                            <option value="delivery" {{ old('order_type', $promotion->order_type) === 'delivery' ? 'selected' : '' }}>Delivery</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{translate('Highlight group')}}</label>
                        <select name="highlight_group" class="custom-select">
                            <option value="1" {{ old('highlight_group', $promotion->highlight_group ?? 1) == 1 ? 'selected' : '' }}>Group 1</option>
                            <option value="2" {{ old('highlight_group', $promotion->highlight_group) == 2 ? 'selected' : '' }}>Group 2</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{translate('start')}} {{translate('date')}}</label>
                        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', optional($promotion->start_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{translate('expire')}} {{translate('date')}}</label>
                        <input type="date" name="end_date" class="form-control" value="{{ old('end_date', optional($promotion->end_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4 form-group d-flex flex-column justify-content-center gap-2">
                        <label class="toggle-switch">
                            <input type="checkbox" name="once_per_customer" class="toggle-switch-input" value="1" {{ old('once_per_customer', $promotion->once_per_customer) ? 'checked' : '' }}>
                            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span><span class="toggle-switch-content">{{translate('Only once per client')}}</span></span>
                        </label>
                        <label class="toggle-switch">
                            <input type="checkbox" name="is_exclusive" class="toggle-switch-input" value="1" {{ old('is_exclusive', $promotion->is_exclusive) ? 'checked' : '' }}>
                            <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span><span class="toggle-switch-content">{{translate('Exclusive')}}</span></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3">
                <a href="{{ route('admin.promotion.list') }}" class="btn btn-secondary">{{translate('back')}}</a>
                <button type="submit" class="btn btn-primary">{{translate('submit')}}</button>
            </div>
        </form>
    </div>
@endsection

@push('script_2')
    <script>
        $(document).ready(function () {
            $('.js-select2-custom').select2({ width: '100%' });
        });
    </script>
@endpush
