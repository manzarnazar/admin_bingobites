@extends('layouts.admin.app')

@section('title', translate('Update banner'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/banner.png')}}" alt="">
                <span class="page-header-title">{{translate('Update_Banner')}}</span>
            </h2>
        </div>

        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <form action="{{route('admin.banner.update',[$banner['id']])}}" method="post" enctype="multipart/form-data">
                    @csrf @method('put')
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="input-label">{{translate('title')}}<span class="text-danger ml-1">*</span></label>
                                        <input type="text" name="title" value="{{$banner['title']}}" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="input-label">{{translate('headline')}}</label>
                                        <input type="text" name="headline" value="{{$banner['headline']}}" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label class="input-label">{{translate('CTA_Label')}}</label>
                                        <input type="text" name="cta_label" value="{{$banner['cta_label'] ?? 'Order Now'}}" class="form-control">
                                    </div>
                                    @php
                                        $linkType = $banner['link_type'] ?? ($banner['promotion_id'] ? 'promotion' : ($banner['product_id'] ? 'product' : ($banner['category_id'] ? 'category' : 'none')));
                                    @endphp
                                    <div class="form-group">
                                        <label class="input-label">{{translate('link_type')}} *</label>
                                        <select name="link_type" class="custom-select" id="link_type">
                                            <option value="none" {{ $linkType === 'none' ? 'selected' : '' }}>{{translate('None')}}</option>
                                            <option value="product" {{ $linkType === 'product' ? 'selected' : '' }}>{{translate('product')}}</option>
                                            <option value="category" {{ $linkType === 'category' ? 'selected' : '' }}>{{translate('category')}}</option>
                                            <option value="promotion" {{ $linkType === 'promotion' ? 'selected' : '' }}>{{translate('Promotion_Deal')}}</option>
                                        </select>
                                    </div>
                                    <div class="form-group {{ $linkType === 'product' ? '' : 'd-none' }}" id="type-product">
                                        <label class="input-label">{{translate('product')}}</label>
                                        <select name="product_id" class="custom-select">
                                            @foreach($products as $product)
                                                <option value="{{$product['id']}}" {{$banner['product_id']==$product['id']?'selected':''}}>{{$product['name']}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group {{ $linkType === 'category' ? '' : 'd-none' }}" id="type-category">
                                        <label class="input-label">{{translate('category')}}</label>
                                        <select name="category_id" class="custom-select">
                                            @foreach($categories as $category)
                                                <option value="{{$category['id']}}" {{$banner['category_id']==$category['id']?'selected':''}}>{{$category['name']}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group {{ $linkType === 'promotion' ? '' : 'd-none' }}" id="type-promotion">
                                        <label class="input-label">{{translate('Promotion_Deal')}}</label>
                                        <select name="promotion_id" class="custom-select">
                                            @foreach($promotions as $promotion)
                                                <option value="{{ $promotion->id }}" {{ $banner['promotion_id'] == $promotion->id ? 'selected' : '' }}>
                                                    {{ $promotion->title }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="input-label">{{translate('banner_Image')}}</label>
                                        <div class="d-flex justify-content-center mt-4">
                                            <div class="upload-file cmn_focus rounded">
                                                <input type="file" name="image" accept="image/*" class="upload-file__input">
                                                <div class="upload-file__img_drag upload-file__img max-h-200px overflow-hidden">
                                                    <img width="465" src="{{$banner->imageFullPath}}" alt="{{ translate('banner image') }}"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <button type="reset" class="btn btn-secondary">{{translate('reset')}}</button>
                                <button type="submit" class="btn btn-primary">{{translate('update')}}</button>
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
    </script>
@endpush
