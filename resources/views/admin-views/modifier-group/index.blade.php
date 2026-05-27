@extends('layouts.admin.app')

@section('title', translate('Modifier Groups'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4 justify-content-between">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <i class="tio-puzzle-outlined"></i>
                {{ translate('Modifier_Groups') }}
            </h2>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#bulkAttachModal">
                    {{ translate('Attach_To_Products') }}
                </button>
                <a href="{{ route('admin.modifier-group.create') }}" class="btn btn-primary">
                    <i class="tio-add"></i> {{ translate('Create_Template') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-top px-card pt-4">
                <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <h5 class="mb-0">
                        {{ translate('Template_List') }}
                        <span class="badge badge-soft-dark rounded-50 fz-12">{{ $groups->total() }}</span>
                    </h5>
                    <form action="{{ url()->current() }}" method="GET">
                        <div class="input-group">
                            <input type="search" name="search" class="form-control" value="{{ $search }}"
                                   placeholder="{{ translate('Search by template name') }}" autocomplete="off">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">{{ translate('Search') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="py-4">
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Template') }}</th>
                            <th>{{ translate('Selection') }}</th>
                            <th>{{ translate('Min_Max') }}</th>
                            <th>{{ translate('Options') }}</th>
                            <th>{{ translate('Attached_Products') }}</th>
                            <th class="text-center">{{ translate('action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($groups as $key => $group)
                            <tr>
                                <td>{{ $groups->firstItem() + $key }}</td>
                                <td>
                                    <div class="font-weight-semibold">{{ $group->name }}</div>
                                    <small class="text-muted">{{ $group->is_required ? translate('Required') : translate('Optional') }}</small>
                                </td>
                                <td>{{ $group->selection_type === 'single' ? translate('Single') : translate('Multiple') }}</td>
                                <td>{{ $group->min }} / {{ $group->max }}</td>
                                <td>{{ $group->options_count }}</td>
                                <td>{{ $group->products_count }}</td>
                                <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <a class="btn btn-outline-info btn-sm square-btn" href="{{ route('admin.modifier-group.edit', $group->id) }}">
                                            <i class="tio-edit"></i>
                                        </a>
                                        <button class="btn btn-outline-danger btn-sm square-btn" type="button"
                                                onclick="form_alert('modifier-group-{{ $group->id }}','{{ translate('Want to delete this template') }} ?')">
                                            <i class="tio-delete"></i>
                                        </button>
                                        <form id="modifier-group-{{ $group->id }}" method="post" action="{{ route('admin.modifier-group.delete', $group->id) }}">
                                            @csrf
                                            @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="table-responsive mt-4 px-3">
                    <div class="d-flex justify-content-lg-end">
                        {!! $groups->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkAttachModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="mb-0">{{ translate('Attach_Template_To_Multiple_Products') }}</h4>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <form action="{{ route('admin.modifier-group.bulk-attach') }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="input-label">{{ translate('Modifier_Group') }}</label>
                            <select name="modifier_group_id" class="form-control js-select2-custom" required>
                                <option value="" selected disabled>---{{ translate('Select') }}---</option>
                                @foreach($allGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{ translate('Category') }} ({{ translate('optional') }})</label>
                            <select name="category_id" class="form-control js-select2-custom">
                                <option value="">{{ translate('Select') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ translate('Use category for bulk migration without selecting each product.') }}</small>
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{ translate('Products') }} ({{ translate('optional') }})</label>
                            <select name="product_ids[]" class="form-control js-select2-custom" multiple>
                                @foreach(\App\Model\Product::withoutGlobalScopes()->orderBy('name')->get(['id', 'name']) as $product)
                                    <option value="{{ $product->id }}">#{{ $product->id }} - {{ $product->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ translate('You can choose products directly or only choose category.') }}</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">{{ translate('cancel') }}</button>
                        <button class="btn btn-primary" type="submit">{{ translate('Attach') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
