@extends('layouts.admin.app')

@section('title', translate('Update banner'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/banner.png')}}" alt="">
                <span class="page-header-title">{{ translate('Update Promo Banner') }}</span>
            </h2>
        </div>

        <div class="row gx-2 gx-lg-3">
            <div class="col-12 mb-3 mb-lg-2">
                <form action="{{ route('admin.banner.update', [$banner->id]) }}" method="post" enctype="multipart/form-data" id="promo-banner-form">
                    @csrf @method('put')
                    <div class="card">
                        <div class="card-body">
                            @include('admin-views.banner.partials.form')
                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <a href="{{ route('admin.banner.list') }}" class="btn btn-secondary cmn_focus">{{ translate('back') }}</a>
                                <button type="submit" class="btn btn-primary cmn_focus">{{ translate('update') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        window.bannerPromoConfig = {
            productVariationsUrl: "{{ url('admin/banner/product-variations') }}",
            groupOneCount: {{ Schema::hasTable('banner_group_items') ? $banner->groupItems->where('group_number', 1)->count() : 0 }},
            groupTwoCount: {{ Schema::hasTable('banner_group_items') ? $banner->groupItems->where('group_number', 2)->count() : 0 }},
        };
    </script>
    <script src="{{ asset('public/assets/admin/js/banner-promo.js') }}"></script>
@endpush
