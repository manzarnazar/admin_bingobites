@extends('layouts.admin.app')

@section('title', translate('Update Modifier Template'))

@push('css_or_js')
    @include('admin-views.modifier-template.partials.template-item-styles')
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/product.png')}}" alt="">
                <span class="page-header-title">
                    {{translate('Modifier_Template_Update')}}
                </span>
            </h2>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.modifier-template.update',[$template->id])}}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="input-label">{{translate('Template Name')}}</label>
                            <input type="text" name="name" class="form-control" value="{{$template->name}}" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="input-label">{{translate('Selection Type')}}</label>
                            <select name="selection_type" class="form-control" required>
                                <option value="multi" {{$template->selection_type === 'multi' ? 'selected' : ''}}>{{translate('Multiple')}}</option>
                                <option value="single" {{$template->selection_type === 'single' ? 'selected' : ''}}>{{translate('Single')}}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="input-label">{{translate('Minimum Selection')}}</label>
                            <input type="number" min="0" name="min_select" class="form-control" value="{{$template->min_select}}" required>
                            <small class="text-muted">{{translate('Total quantity across all choices in this group (customer app).')}}</small>
                        </div>
                        <div class="col-md-4">
                            <label class="input-label">{{translate('Maximum Selection')}}</label>
                            <input type="number" min="0" name="max_select" class="form-control" value="{{$template->max_select}}" required>
                            <small class="text-muted">{{translate('Total quantity across all choices in this group (customer app).')}}</small>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="d-flex gap-4 pb-2">
                                <label class="mb-0"><input type="checkbox" name="is_required" {{$template->is_required ? 'checked' : ''}}> {{translate('Required')}}</label>
                                <label class="mb-0"><input type="checkbox" name="is_active" {{$template->is_active ? 'checked' : ''}}> {{translate('Active')}}</label>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">{{translate('Template Items')}}</h5>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-template-item-row">
                            {{translate('Add Item')}}
                        </button>
                    </div>

                    <div class="template-items-header d-none d-md-grid">
                        <span>{{ translate('Addon') }}</span>
                        <span>{{ translate('Sort') }}</span>
                        <span>{{ translate('Options') }}</span>
                        <span></span>
                    </div>
                    <div id="template-item-rows">
                        @foreach($template->items as $index => $item)
                            @include('admin-views.modifier-template.partials.template-item-row', ['index' => $index, 'addons' => $addons, 'item' => $item])
                        @endforeach
                    </div>

                    <hr>
                    <div class="form-group mb-0">
                        <label class="input-label">{{translate('Assign Products')}}</label>
                        <small class="d-block text-muted mb-2">
                            {{translate('Attach this template to one or more products at once.')}}
                        </small>
                        <select name="product_ids[]" class="form-control" id="choose_template_products" multiple="multiple">
                            @php($selectedProductIds = collect(old('product_ids', $template->products->pluck('id'))))
                            @foreach($products as $product)
                                <option value="{{$product->id}}" {{$selectedProductIds->contains($product->id) ? 'selected' : ''}}>
                                    {{$product->name}}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="{{route('admin.modifier-template.index')}}" class="btn btn-secondary">{{translate('cancel')}}</a>
                        <button type="submit" class="btn btn-primary">{{translate('update')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.modifier-template.partials.template-item-scripts')
    <script>
        "use strict";
        let modifierTemplateRow = {{ max($template->items->count(), 1) }};

        initTemplateItemRows();

        $('#add-template-item-row').on('click', function () {
            const rowHtml = getTemplateItemRowHtml(modifierTemplateRow);
            const $row = $(rowHtml);
            $('#template-item-rows').append($row);
            toggleTemplateItemRow($row);
            modifierTemplateRow++;
        });

        $(document).on('change', '.template-addon-select', function () {
            toggleTemplateItemRow($(this).closest('.template-item-row'));
        });

        $(document).on('click', '.remove-template-item-row', function () {
            if ($('.template-item-row').length > 1) {
                $(this).closest('.template-item-row').remove();
            }
        });

        $("#choose_template_products").select2({
            placeholder: "{{translate('Select Products')}}",
            allowClear: true
        });
    </script>
@endpush
