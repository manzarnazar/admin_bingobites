@extends('layouts.admin.app')

@section('title', translate('Add new banner'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/banner.png')}}" alt="">
                <span class="page-header-title">{{translate('Add_New_Banner')}}</span>
            </h2>
        </div>

        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <form action="{{route('admin.banner.store')}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="card banner-form">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="input-label">{{translate('title')}}</label>
                                        <input type="text" name="title" class="form-control" placeholder="{{translate('New banner')}}" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="input-label">{{translate('headline')}}</label>
                                        <input type="text" name="headline" class="form-control" placeholder="Buy 1 Burger, Get 1 Free">
                                    </div>
                                    <div class="form-group">
                                        <label class="input-label">{{translate('CTA_Label')}}</label>
                                        <input type="text" name="cta_label" class="form-control" value="Order Now">
                                    </div>
                                    <div class="form-group">
                                        <label class="input-label">{{translate('link_type')}} *</label>
                                        <select name="link_type" class="custom-select" id="link_type">
                                            <option value="none">{{translate('None')}}</option>
                                            <option value="product">{{translate('product')}}</option>
                                            <option value="category">{{translate('category')}}</option>
                                            <option value="promotion">{{translate('Promotion_Deal')}}</option>
                                        </select>
                                    </div>
                                    <div class="form-group d-none" id="type-product">
                                        <label class="input-label">{{translate('product')}}</label>
                                        <select name="product_id" class="custom-select">
                                            @foreach($products as $product)
                                                <option value="{{$product['id']}}">{{$product['name']}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group d-none" id="type-category">
                                        <label class="input-label">{{translate('category')}}</label>
                                        <select name="category_id" class="custom-select">
                                            @foreach($categories as $category)
                                                <option value="{{$category['id']}}">{{$category['name']}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group d-none" id="type-promotion">
                                        <label class="input-label">{{translate('Promotion_Deal')}}</label>
                                        <select name="promotion_id" class="custom-select">
                                            @foreach($promotions as $promotion)
                                                <option value="{{ $promotion->id }}">{{ $promotion->title }} @if($promotion->headline)- {{ $promotion->headline }}@endif</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <label class="mb-0">{{translate('banner_Image')}}</label>
                                            <small class="text-danger">* ( {{translate('ratio 2:1')}} )</small>
                                        </div>
                                        <div class="d-flex justify-content-center mt-4">
                                            <div class="upload-file">
                                                <input type="file" name="image" accept="image/*" class="upload-file__input" required>
                                                <div class="upload-file__img_drag upload-file__img">
                                                    <img width="465" id="viewer" src="{{asset('public/assets/admin/img/icons/upload_img2.png')}}" alt="">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <button type="reset" class="btn btn-secondary">{{translate('reset')}}</button>
                                <button type="submit" class="btn btn-primary">{{translate('submit')}}</button>
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
        function showLinkType(type) {
            $('#type-product, #type-category, #type-promotion').addClass('d-none');
            if (type === 'product') $('#type-product').removeClass('d-none');
            if (type === 'category') $('#type-category').removeClass('d-none');
            if (type === 'promotion') $('#type-promotion').removeClass('d-none');
        }
        $('#link_type').on('change', function () { showLinkType($(this).val()); });
        showLinkType($('#link_type').val());
    </script>
@endpush
