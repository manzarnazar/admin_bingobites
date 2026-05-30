@extends('layouts.admin.app')

@section('title', translate('Modifier Templates'))

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
                    {{translate('Modifier_Templates')}}
                </span>
            </h2>
        </div>

        <div class="card">
            <div class="card-top px-card pt-4">
                <div class="d-flex flex-column flex-md-row flex-wrap gap-3 justify-content-md-between align-items-md-center">
                    <h5 class="d-flex align-items-center gap-2">
                        {{translate('Template_Table')}}
                        <span class="badge badge-soft-dark rounded-50 fz-12">{{ $templates->total() }}</span>
                    </h5>

                    <div class="d-flex flex-wrap justify-content-md-end gap-3">
                        <form action="{{url()->current()}}" method="GET">
                            <div class="input-group">
                                <input type="search" name="search" class="form-control" placeholder="{{translate('Search by template name')}}" value="{{$search}}" autocomplete="off">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">{{translate('Search')}}</button>
                                </div>
                            </div>
                        </form>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addModifierTemplateModal">
                            <i class="tio-add"></i>
                            {{translate('Add_Template')}}
                        </button>
                    </div>
                </div>
            </div>

            <div class="py-4">
                <div class="table-responsive datatable-custom">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>{{translate('SL')}}</th>
                            <th>{{translate('name')}}</th>
                            <th>{{translate('selection_type')}}</th>
                            <th>{{translate('rule')}}</th>
                            <th>{{translate('items')}}</th>
                            <th>{{translate('products')}}</th>
                            <th class="text-center">{{translate('status')}}</th>
                            <th class="text-center">{{translate('action')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($templates as $key => $template)
                            <tr>
                                <td>{{$templates->firstItem() + $key}}</td>
                                <td>
                                    <div class="font-weight-semibold">{{$template->name}}</div>
                                </td>
                                <td class="text-capitalize">{{$template->selection_type}}</td>
                                <td>{{$template->min_select}} - {{$template->max_select}}</td>
                                <td>
                                    <div class="line--limit-2">
                                        {{ $template->items->pluck('addon.name')->filter()->implode(', ') }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-soft-info">{{ $template->products_count }} {{translate('products')}}</span>
                                </td>
                                <td class="text-center">
                                    <label class="toggle-switch toggle-switch-sm" for="status-{{$template->id}}">
                                        <input type="checkbox"
                                               onclick="location.href='{{route('admin.modifier-template.status',[$template->id,$template->is_active?0:1])}}'"
                                               class="toggle-switch-input"
                                               id="status-{{$template->id}}"
                                            {{$template->is_active ? 'checked' : ''}}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <a class="btn btn-outline-info btn-sm square-btn" href="{{route('admin.modifier-template.edit',[$template->id])}}">
                                            <i class="tio-edit"></i>
                                        </a>
                                        <button class="btn btn-outline-danger btn-sm square-btn" type="button"
                                                onclick="form_alert('modifier-template-{{$template->id}}','{{translate('Want to delete this template')}} ?')">
                                            <i class="tio-delete"></i>
                                        </button>
                                    </div>
                                    <form action="{{route('admin.modifier-template.delete',[$template->id])}}"
                                          method="post" id="modifier-template-{{$template->id}}">
                                        @csrf @method('delete')
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="table-responsive mt-4 px-3">
                    <div class="d-flex justify-content-lg-end">
                        {!! $templates->links() !!}
                    </div>
                </div>

                @if(count($templates) == 0)
                    <div class="text-center p-4">
                        <img class="w-120px mb-3" src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="">
                        <p class="mb-0">{{translate('No_data_to_show')}}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="addModifierTemplateModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <form action="{{route('admin.modifier-template.store')}}" method="post">
                        @csrf
                        <h4 class="mb-4">{{translate('Create Modifier Template')}}</h4>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="input-label">{{translate('Template Name')}}</label>
                                <input type="text" name="name" class="form-control" required maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="input-label">{{translate('Selection Type')}}</label>
                                <select name="selection_type" class="form-control" required>
                                    <option value="multi">{{translate('Multiple')}}</option>
                                    <option value="single">{{translate('Single')}}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="input-label">{{translate('Minimum Selection')}}</label>
                                <input type="number" min="0" name="min_select" class="form-control" value="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="input-label">{{translate('Maximum Selection')}}</label>
                                <input type="number" min="0" name="max_select" class="form-control" value="1" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="d-flex gap-4 pb-2">
                                    <label class="d-flex align-items-center gap-2 mb-0">
                                        <input type="checkbox" name="is_required">
                                        <span>{{translate('Required')}}</span>
                                    </label>
                                    <label class="d-flex align-items-center gap-2 mb-0">
                                        <input type="checkbox" name="is_active" checked>
                                        <span>{{translate('Active')}}</span>
                                    </label>
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
                        <div id="template-item-rows">
                            @include('admin-views.modifier-template.partials.template-item-row', ['index' => 0, 'addons' => $addons])
                        </div>

                        <hr>
                        <div class="form-group mb-0">
                            <label class="input-label">{{translate('Assign Products')}}</label>
                            <small class="d-block text-muted mb-2">
                                {{translate('Attach this template to one or more products at once.')}}
                            </small>
                            <select name="product_ids[]" class="form-control" id="choose_template_products" multiple="multiple">
                                @foreach($products as $product)
                                    <option value="{{$product->id}}" {{collect(old('product_ids', []))->contains($product->id) ? 'selected' : ''}}>
                                        {{$product->name}}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="d-flex justify-content-end gap-3 mt-4">
                            <button type="reset" class="btn btn-secondary">{{translate('reset')}}</button>
                            <button type="submit" class="btn btn-primary">{{translate('submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.modifier-template.partials.template-item-scripts')
    <script>
        "use strict";
        let modifierTemplateRow = 1;

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
            allowClear: true,
            dropdownParent: $('#addModifierTemplateModal')
        });
    </script>
@endpush
