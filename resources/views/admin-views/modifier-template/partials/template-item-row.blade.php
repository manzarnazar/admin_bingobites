@php
    $selectedAddonId = isset($item) ? (string) $item->add_on_id : (string) (old("items.{$index}.add_on_id") ?? '');
    $newName = old("items.{$index}.new_name", '');
    $newPrice = old("items.{$index}.new_price", '');
    $isNewMode = $selectedAddonId === 'new' || ($selectedAddonId === '' && ($newName !== '' || $newPrice !== ''));
    $sortOrder = isset($item) ? $item->sort_order : (old("items.{$index}.sort_order") ?? '');
    $isDefault = isset($item) ? $item->is_default : old("items.{$index}.is_default");
    $isActive = isset($item) ? $item->is_active : (old("items.{$index}.is_active") !== null ? old("items.{$index}.is_active") : true);
@endphp
<div class="template-item-row" data-row="{{ $index }}">
    <div class="template-addon-cell">
        <select name="items[{{ $index }}][add_on_id]" class="form-control form-control-sm template-addon-select">
            <option value="">{{ translate('Select Addon') }}</option>
            <option value="new" {{ $isNewMode ? 'selected' : '' }}>{{ translate('Create new addon') }}</option>
            @foreach($addons as $addon)
                <option value="{{ $addon->id }}" {{ !$isNewMode && $selectedAddonId === (string) $addon->id ? 'selected' : '' }}>
                    {{ $addon->name }}
                </option>
            @endforeach
        </select>
        <div class="new-addon-fields {{ $isNewMode ? '' : 'd-none' }}">
            <input type="text"
                   name="items[{{ $index }}][new_name]"
                   class="form-control form-control-sm template-new-name"
                   placeholder="{{ translate('Addon name') }}"
                   value="{{ $newName }}"
                   maxlength="255">
            <input type="number"
                   step="any"
                   min="0"
                   name="items[{{ $index }}][new_price]"
                   class="form-control form-control-sm template-new-price"
                   placeholder="{{ translate('Price') }}"
                   value="{{ $newPrice }}">
        </div>
    </div>
    <div>
        <input type="number"
               min="0"
               class="form-control form-control-sm template-sort-input"
               name="items[{{ $index }}][sort_order]"
               value="{{ $sortOrder }}"
               placeholder="{{ translate('Sort') }}">
    </div>
    <div class="template-toggles">
        <label class="template-toggle-label">
            <input type="checkbox" name="items[{{ $index }}][is_default]" {{ $isDefault ? 'checked' : '' }}>
            {{ translate('Default') }}
        </label>
        <label class="template-toggle-label">
            <input type="checkbox" name="items[{{ $index }}][is_active]" {{ $isActive ? 'checked' : '' }}>
            {{ translate('Active') }}
        </label>
    </div>
    <div class="template-delete-btn">
        <button type="button" class="btn btn-outline-danger btn-sm remove-template-item-row" title="{{ translate('Remove') }}">
            <i class="tio-delete"></i>
        </button>
    </div>
</div>
