@extends('layouts.admin.app')

@section('title', translate('Promotion Deals'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4 justify-content-between">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/campaign.png')}}" alt="">
                <span class="page-header-title">{{translate('Promotion_Deals')}}</span>
            </h2>
            <a href="{{ route('admin.promotion-deal.create') }}" class="btn btn-primary">
                <i class="tio-add"></i> {{translate('Add_New_Promotion')}}
            </a>
        </div>

        <div class="card">
            <div class="card-top px-card pt-4">
                <form action="{{ url()->current() }}" method="GET" class="mb-3">
                    <div class="input-group" style="max-width: 420px;">
                        <input type="search" name="search" class="form-control" placeholder="{{translate('Search promotions')}}" value="{{ $search }}">
                        <button type="submit" class="btn btn-primary">{{translate('Search')}}</button>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{translate('SL')}}</th>
                        <th>{{translate('title')}}</th>
                        <th>{{translate('type')}}</th>
                        <th>{{translate('status')}}</th>
                        <th class="text-center">{{translate('action')}}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($promotions as $key => $promotion)
                        <tr>
                            <td>{{ $promotions->firstItem() + $key }}</td>
                            <td>
                                <div class="font-weight-semibold">{{ $promotion->title }}</div>
                                <small class="text-muted">{{ $promotion->headline }}</small>
                            </td>
                            <td>{{ str_replace('_', ' ', $promotion->type) }}</td>
                            <td>
                                <label class="toggle-switch toggle-switch-sm">
                                    <input type="checkbox" class="toggle-switch-input redirect-url"
                                           data-url="{{ route('admin.promotion-deal.status', ['id' => $promotion->id, 'status' => $promotion->status ? 0 : 1]) }}"
                                        {{ $promotion->status ? 'checked' : '' }}>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                </label>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.promotion-deal.edit', $promotion->id) }}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <form action="{{ route('admin.promotion-deal.delete') }}" method="post" onsubmit="return confirm('{{ translate('Want to delete this promotion ?') }}')">
                                        @csrf @method('delete')
                                        <input type="hidden" name="id" value="{{ $promotion->id }}">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="tio-delete"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $promotions->links() }}</div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        $('.redirect-url').on('change', function () {
            window.location.href = $(this).data('url');
        });
    </script>
@endpush
