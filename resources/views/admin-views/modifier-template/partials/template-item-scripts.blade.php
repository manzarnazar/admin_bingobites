<script>
    "use strict";

    function toggleTemplateItemRow($row) {
        const isNew = $row.find('.template-addon-select').val() === 'new';
        const $newFields = $row.find('.new-addon-fields');
        const $select = $row.find('.template-addon-select');
        const $newName = $row.find('.template-new-name');
        const $newPrice = $row.find('.template-new-price');

        if (isNew) {
            $newFields.removeClass('d-none');
            $select.prop('required', false);
            $newName.prop('required', true);
            $newPrice.prop('required', true);
        } else {
            $newFields.addClass('d-none');
            $select.prop('required', true);
            $newName.prop('required', false);
            $newPrice.prop('required', false);
        }
    }

    function initTemplateItemRows() {
        $('.template-item-row').each(function () {
            toggleTemplateItemRow($(this));
        });
    }

    function getTemplateItemRowHtml(index) {
        const addonOptions = `{!! $addons->map(function ($addon) { return '<option value="' . $addon->id . '">' . e($addon->name) . '</option>'; })->implode('') !!}`;
        return `
            <div class="row g-2 template-item-row mb-2" data-row="${index}">
                <div class="col-md-4">
                    <select name="items[${index}][add_on_id]" class="form-control template-addon-select">
                        <option value="">{{ translate('Select Addon') }}</option>
                        <option value="new">{{ translate('Create new addon') }}</option>
                        ${addonOptions}
                    </select>
                </div>
                <div class="col-md-4 new-addon-fields d-none">
                    <div class="row g-1">
                        <div class="col-7">
                            <input type="text" name="items[${index}][new_name]" class="form-control template-new-name" placeholder="{{ translate('Addon name') }}" maxlength="255">
                        </div>
                        <div class="col-5">
                            <input type="number" step="any" min="0" name="items[${index}][new_price]" class="form-control template-new-price" placeholder="{{ translate('Price') }}">
                        </div>
                    </div>
                </div>
                <div class="col-md-1">
                    <input type="number" min="0" class="form-control" name="items[${index}][sort_order]" placeholder="{{ translate('Sort') }}">
                </div>
                <div class="col-md-2 d-flex align-items-center">
                    <label class="template-toggle-label"><input type="checkbox" name="items[${index}][is_default]"> {{ translate('Default') }}</label>
                </div>
                <div class="col-md-1">
                    <div class="template-item-actions">
                        <label class="template-toggle-label"><input type="checkbox" name="items[${index}][is_active]" checked> {{ translate('Active') }}</label>
                        <button type="button" class="btn btn-danger btn-sm remove-template-item-row"><i class="tio-delete"></i></button>
                    </div>
                </div>
            </div>`;
    }
</script>
