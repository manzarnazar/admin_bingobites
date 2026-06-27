<div class="promo-group-item card mb-2" data-product-id="{{ $item->product_id }}">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <strong>{{ $item->product->name ?? ($products->firstWhere('id', $item->product_id)->name ?? 'Product #' . $item->product_id) }}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-group-item">{{ translate('remove') }}</button>
        </div>
        <input type="hidden" name="group_{{ $group }}[{{ $index }}][product_id]" value="{{ $item->product_id }}">
        <div class="variation-fields" data-product-id="{{ $item->product_id }}">
            @php $savedVariations = collect($item->variations ?? []); @endphp
            @foreach($savedVariations as $vIndex => $variation)
                <div class="form-group mb-2">
                    <label class="input-label">{{ $variation['name'] ?? 'Variation' }}</label>
                    <input type="hidden" name="group_{{ $group }}[{{ $index }}][variations][{{ $vIndex }}][name]" value="{{ $variation['name'] ?? '' }}">
                    <input type="text"
                           name="group_{{ $group }}[{{ $index }}][variations][{{ $vIndex }}][values][label][]"
                           class="form-control"
                           value="{{ $variation['values']['label'][0] ?? '' }}"
                           placeholder="{{ translate('Selected option label') }}">
                </div>
            @endforeach
        </div>
    </div>
</div>
