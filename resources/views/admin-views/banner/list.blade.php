@extends('layouts.admin.app')

@section('title', translate('Banner list'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/banner.png')}}" alt="">
                <span class="page-header-title">{{ translate('Promo Banner Setup') }}</span>
            </h2>
        </div>

        <div class="row g-2">
            <div class="col-12 mb-3 mb-lg-2">
                <form action="{{ route('admin.banner.store') }}" method="post" enctype="multipart/form-data" id="promo-banner-form">
                    @csrf
                    <div class="card banner-form">
                        <div class="card-body">
                            @include('admin-views.banner.partials.form')
                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <button type="reset" class="btn btn-secondary cmn_focus">{{ translate('reset') }}</button>
                                <button type="submit" class="btn btn-primary cmn_focus">{{ translate('submit') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-top px-card pt-4">
                        <div class="row align-items-center gy-2">
                            <div class="col-sm-4 col-md-6 col-lg-8">
                                <h5 class="d-flex align-items-center gap-2 mb-0">
                                    {{ translate('Banner_List') }}
                                    <span class="badge badge-soft-dark rounded-50 fz-12">{{ $banners->total() }}</span>
                                </h5>
                            </div>
                            <div class="col-sm-8 col-md-6 col-lg-4">
                                <form action="{{ url()->current() }}" method="GET">
                                    <div class="input-group">
                                        <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ translate('Search_by_Title') }}" autocomplete="off">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">{{ translate('Search') }}</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="py-4">
                        <div class="table-responsive datatable-custom">
                            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                                <thead class="thead-light">
                                <tr>
                                    <th>{{ translate('SL') }}</th>
                                    <th>{{ translate('Banner_Image') }}</th>
                                    <th>{{ translate('Title') }}</th>
                                    <th>{{ translate('headline') }}</th>
                                    <th>{{ translate('Promotion Type') }}</th>
                                    <th>{{ translate('status') }}</th>
                                    <th class="text-center">{{ translate('action') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($banners as $key => $banner)
                                    <tr>
                                        <td>{{ $banners->firstItem() + $key }}</td>
                                        <td><img class="img-vertical-150" src="{{ $banner->imageFullPath }}" alt=""></td>
                                        <td><div class="max-w300 text-wrap">{{ $banner->title }}</div></td>
                                        <td>{{ $banner->headline ?? $banner->title }}</td>
                                        <td>{{ str_replace('_', ' ', $banner->promotion_type ?? 'bogo') }}</td>
                                        <td>
                                            <label class="switcher">
                                                <input class="switcher_input status-change" type="checkbox" {{ $banner->status == 1 ? 'checked' : '' }} id="{{ $banner->id }}" data-url="{{ route('admin.banner.status', [$banner->id, 0]) }}">
                                                <span class="switcher_control"></span>
                                            </label>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a class="btn btn-outline-info btn-sm edit square-btn" href="{{ route('admin.banner.edit', [$banner->id]) }}"><i class="tio-edit"></i></a>
                                                <button type="button" class="btn btn-outline-danger btn-sm delete square-btn form-alert" data-id="banner-{{ $banner->id }}" data-message="{{ translate('Want to delete this banner') }}"><i class="tio-delete"></i></button>
                                            </div>
                                            <form action="{{ route('admin.banner.delete', [$banner->id]) }}" method="post" id="banner-{{ $banner->id }}">
                                                @csrf @method('delete')
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="table-responsive mt-4 px-3">
                            <div class="d-flex justify-content-lg-end">{!! $banners->links() !!}</div>
                        </div>

                        @if(count($banners) == 0)
                            <div class="text-center p-4">
                                <img class="w-120px mb-3" src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                                <p class="mb-0">{{ translate('No_data_to_show') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        window.bannerPromoConfig = {
            productVariationsUrl: "{{ url('admin/banner/product-variations') }}",
            products: @json($productsPicker),
            groupOneCount: 0,
            groupTwoCount: 0,
        };
        window.translateRemove = @json(translate('Remove'));
        window.translateGroupProductsRequired = @json(translate('Please add at least one product to Group 1 and Group 2.'));
        window.translateGroupOneTitle = @json(translate('Items Group 1'));
        window.translateGroupTwoTitle = @json(translate('Items Group 2'));
        window.translateAddToGroup = @json(translate('Add to group'));
        window.translateSelectProduct = @json(translate('Select product'));
        window.translateChooseVariation = @json(translate('Choose the variation for this promo item'));
        window.translateNoVariations = @json(translate('This product has no variations. Click Add to include it.'));
        window.translateLoadFailed = @json(translate('Could not load product details. Please try again.'));
    </script>
    <script src="{{ asset('public/assets/admin/js/banner-promo.js') }}?v=4"></script>
@endpush
