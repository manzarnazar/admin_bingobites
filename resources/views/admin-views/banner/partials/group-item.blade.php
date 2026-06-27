@php
    $productModel = $item->product ?? $products->firstWhere('id', $item->product_id);
    $productName = $productModel->name ?? ('Product #' . $item->product_id);
    $productImage = $productModel?->imageFullPath ?? asset('public/assets/admin/img/160x160/img2.jpg');
    $savedMap = [];
    foreach ($item->variations ?? [] as $variation) {
        $savedMap[$variation['name'] ?? ''] = $variation['values']['label'][0] ?? '';
    }
    $productVariations = json_decode($productModel->variations ?? '[]', true) ?: [];
    $variationIndex = 0;
@endphp
<div class="promo-group-item card mb-2" data-product-id="{{ $item->product_id }}">
    <div class="card-body py-3">
        <div class="d-flex gap-3">
            <img src="{{ $productImage }}" alt="{{ $productName }}" class="promo-group-item-thumb rounded border">
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <strong class="text-wrap">{{ $productName }}</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-group-item flex-shrink-0">{{ translate('remove') }}</button>
                </div>
                <input type="hidden" name="group_{{ $group }}[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                <div class="variation-fields" data-product-id="{{ $item->product_id }}">
                    @if(count($productVariations) > 0)
                        @foreach($productVariations as $variation)
                            @if(isset($variation['price']))
                                @continue
                            @endif
                            @php
                                $options = $variation['values'] ?? [];
                                $selected = $savedMap[$variation['name'] ?? ''] ?? ($options[0]['label'] ?? '');
                            @endphp
                            <div class="form-group mb-2">
                                <label class="input-label mb-1">{{ $variation['name'] ?? translate('Variation') }}</label>
                                <input type="hidden" name="group_{{ $group }}[{{ $index }}][variations][{{ $variationIndex }}][name]" value="{{ $variation['name'] ?? '' }}">
                                <select name="group_{{ $group }}[{{ $index }}][variations][{{ $variationIndex }}][values][label][]" class="form-control form-control-sm" required>
                                    @foreach($options as $option)
                                        @php $label = $option['label'] ?? $option['level'] ?? ''; @endphp
                                        <option value="{{ $label }}" {{ $label === $selected ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @php $variationIndex++; @endphp
                        @endforeach
                    @else
                        @foreach($item->variations ?? [] as $variation)
                            <div class="form-group mb-2">
                                <label class="input-label mb-1">{{ $variation['name'] ?? translate('Variation') }}</label>
                                <input type="hidden" name="group_{{ $group }}[{{ $index }}][variations][{{ $variationIndex }}][name]" value="{{ $variation['name'] ?? '' }}">
                                <input type="text"
                                       name="group_{{ $group }}[{{ $index }}][variations][{{ $variationIndex }}][values][label][]"
                                       class="form-control form-control-sm"
                                       value="{{ $variation['values']['label'][0] ?? '' }}"
                                       placeholder="{{ translate('Selected option label') }}">
                            </div>
                            @php $variationIndex++; @endphp
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
