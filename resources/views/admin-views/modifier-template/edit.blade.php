@extends('layouts.admin.app')

@section('title', translate('Update Modifier Template'))

@push('css_or_js')
    <style>
        .template-item-row {
            align-items: center;
        }
        .template-toggle-label {
            display: flex;
            align-items: center;
            gap: .4rem;
            white-space: nowrap;
            margin-bottom: 0;
        }
        .template-item-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            flex-wrap: nowrap;
        }
    </style>
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
                        </div>
                        <div class="col-md-4">
                            <label class="input-label">{{translate('Maximum Selection')}}</label>
                            <input type="number" min="0" name="max_select" class="form-control" value="{{$template->max_select}}" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="d-flex gap-4 pb-2">
                                <label class="mb-0"><input type="checkbox" name="is_required" {{$template->is_required ? 'checked' : ''}}> {{translate('Required')}}</label>
                                <label class="mb-0"><input type="checkbox" name="is_active" {{$template->is_active ? 'checked' : ''}}> {{translate('Active')}}</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="input-label">{{translate('Description')}}</label>
                            <textarea name="description" class="form-control" rows="2">{{$template->description}}</textarea>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">{{translate('Template Items')}}</h5>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-template-item-row">
                            {{translate('Add Item')}}
                        </button>
                    </div>

                    <div id="template-item-rows">
                        @foreach($template->items as $index => $item)
                            <div class="row g-2 template-item-row mb-2" data-row="{{$index}}">
                                <div class="col-md-6">
                                    <select name="items[{{$index}}][add_on_id]" class="form-control" required>
                                        <option value="">{{translate('Select Addon')}}</option>
                                        @foreach($addons as $addon)
                                            <option value="{{$addon->id}}" {{$addon->id == $item->add_on_id ? 'selected' : ''}}>
                                                {{$addon->name}}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" min="0" class="form-control" name="items[{{$index}}][sort_order]" value="{{$item->sort_order}}" placeholder="{{translate('Sort')}}">
                                </div>
                                <div class="col-md-2 d-flex align-items-center">
                                    <label class="template-toggle-label"><input type="checkbox" name="items[{{$index}}][is_default]" {{$item->is_default ? 'checked' : ''}}> {{translate('Default')}}</label>
                                </div>
                                <div class="col-md-2">
                                    <div class="template-item-actions">
                                        <label class="template-toggle-label"><input type="checkbox" name="items[{{$index}}][is_active]" {{$item->is_active ? 'checked' : ''}}> {{translate('Active')}}</label>
                                        <button type="button" class="btn btn-danger btn-sm remove-template-item-row"><i class="tio-delete"></i></button>
                                    </div>
                                </div>
                            </div>
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
    <script>
        "use strict";
        let modifierTemplateRow = {{max($template->items->count(), 1)}};

        $('#add-template-item-row').on('click', function () {
            const addonOptions = `{!! $addons->map(function ($addon) { return '<option value="' . $addon->id . '">' . e($addon->name) . '</option>'; })->implode('') !!}`;
            const rowHtml = `
                <div class="row g-2 template-item-row mb-2" data-row="${modifierTemplateRow}">
                    <div class="col-md-6">
                        <select name="items[${modifierTemplateRow}][add_on_id]" class="form-control" required>
                            <option value="">{{translate('Select Addon')}}</option>
                            ${addonOptions}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" min="0" class="form-control" name="items[${modifierTemplateRow}][sort_order]" placeholder="{{translate('Sort')}}">
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <label class="template-toggle-label"><input type="checkbox" name="items[${modifierTemplateRow}][is_default]"> {{translate('Default')}}</label>
                    </div>
                    <div class="col-md-2">
                        <div class="template-item-actions">
                            <label class="template-toggle-label"><input type="checkbox" name="items[${modifierTemplateRow}][is_active]" checked> {{translate('Active')}}</label>
                            <button type="button" class="btn btn-danger btn-sm remove-template-item-row"><i class="tio-delete"></i></button>
                        </div>
                    </div>
                </div>`;
            $('#template-item-rows').append(rowHtml);
            modifierTemplateRow++;
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
