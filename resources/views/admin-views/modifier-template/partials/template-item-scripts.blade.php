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
            <div class="template-item-row" data-row="${index}">
                <div class="template-addon-cell">
                    <select name="items[${index}][add_on_id]" class="form-control form-control-sm template-addon-select">
                        <option value="">{{ translate('Select Addon') }}</option>
                        <option value="new">{{ translate('Create new addon') }}</option>
                        ${addonOptions}
                    </select>
                    <div class="new-addon-fields d-none">
                        <input type="text" name="items[${index}][new_name]" class="form-control form-control-sm template-new-name" placeholder="{{ translate('Addon name') }}" maxlength="255">
                        <input type="number" step="any" min="0" name="items[${index}][new_price]" class="form-control form-control-sm template-new-price" placeholder="{{ translate('Price') }}">
                    </div>
                </div>
                <div>
                    <input type="number" min="0" class="form-control form-control-sm template-sort-input" name="items[${index}][sort_order]" placeholder="{{ translate('Sort') }}">
                </div>
                <div class="template-toggles">
                    <label class="template-toggle-label"><input type="checkbox" name="items[${index}][is_default]"> {{ translate('Default') }}</label>
                    <label class="template-toggle-label"><input type="checkbox" name="items[${index}][is_active]" checked> {{ translate('Active') }}</label>
                </div>
                <div class="template-delete-btn">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-template-item-row" title="{{ translate('Remove') }}">
                        <i class="tio-delete"></i>
                    </button>
                </div>
            </div>`;
    }
</script>
