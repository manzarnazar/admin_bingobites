<div class="modal fade" id="promo-product-picker-modal" tabindex="-1" role="dialog" aria-labelledby="promoProductPickerLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="promoProductPickerLabel">{{ translate('Select product') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="promo-picker-step-products">
                    <div class="form-group">
                        <input type="search" id="promo-picker-search" class="form-control" placeholder="{{ translate('Search products') }}" autocomplete="off">
                    </div>
                    <div id="promo-picker-product-grid" class="promo-product-grid"></div>
                    <div id="promo-picker-empty" class="text-center text-muted py-4 d-none">
                        {{ translate('No products found') }}
                    </div>
                </div>

                <div id="promo-picker-step-variations" class="d-none">
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="promo-picker-back">
                        &larr; {{ translate('Back to products') }}
                    </button>
                    <div class="d-flex align-items-center gap-3 mb-3 p-3 border rounded">
                        <img id="promo-picker-selected-image" src="" alt="" class="promo-picker-selected-thumb rounded">
                        <div>
                            <strong id="promo-picker-selected-name" class="d-block"></strong>
                            <small class="text-muted">{{ translate('Choose the variation for this promo item') }}</small>
                        </div>
                    </div>
                    <div id="promo-picker-variation-fields"></div>
                    <p id="promo-picker-no-variations" class="text-muted mb-0 d-none">
                        {{ translate('This product has no variations. Click Add to include it.') }}
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="btn btn-primary d-none" id="promo-picker-confirm-add">{{ translate('Add to group') }}</button>
            </div>
        </div>
    </div>
</div>

<style>
    .promo-product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 12px;
        max-height: 420px;
        overflow-y: auto;
    }

    .promo-product-card {
        border: 1px solid #e7eaf3;
        border-radius: 10px;
        background: #fff;
        cursor: pointer;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        overflow: hidden;
        text-align: left;
        padding: 0;
    }

    .promo-product-card:hover,
    .promo-product-card:focus {
        border-color: #377dff;
        box-shadow: 0 0 0 2px rgba(55, 125, 255, 0.15);
        outline: none;
    }

    .promo-product-card img {
        width: 100%;
        aspect-ratio: 1;
        object-fit: cover;
        display: block;
        background: #f8fafd;
    }

    .promo-product-card .promo-product-card__name {
        padding: 8px 10px;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.3;
        color: #1e2022;
    }

    .promo-picker-selected-thumb {
        width: 72px;
        height: 72px;
        object-fit: cover;
        flex-shrink: 0;
        background: #f8fafd;
    }

    .promo-group-item-thumb {
        width: 64px;
        height: 64px;
        object-fit: cover;
        flex-shrink: 0;
        background: #f8fafd;
    }
</style>
