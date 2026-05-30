@php
    $selectedAddonId = isset($item) ? (string) $item->add_on_id : (string) (old("items.{$index}.add_on_id") ?? '');
    $newName = old("items.{$index}.new_name", '');
    $newPrice = old("items.{$index}.new_price", '');
    $isNewMode = $selectedAddonId === 'new' || ($selectedAddonId === '' && ($newName !== '' || $newPrice !== ''));
    $sortOrder = isset($item) ? $item->sort_order : (old("items.{$index}.sort_order") ?? '');
    $isDefault = isset($item) ? $item->is_default : old("items.{$index}.is_default");
    $isActive = isset($item) ? $item->is_active : (old("items.{$index}.is_active") !== null ? old("items.{$index}.is_active") : true);
@endphp
<div class="row g-2 template-item-row mb-2" data-row="{{ $index }}">
    <div class="col-md-4">
        <select name="items[{{ $index }}][add_on_id]" class="form-control template-addon-select">
            <option value="">{{ translate('Select Addon') }}</option>
            <option value="new" {{ $isNewMode ? 'selected' : '' }}>{{ translate('Create new addon') }}</option>
            @foreach($addons as $addon)
                <option value="{{ $addon->id }}" {{ !$isNewMode && $selectedAddonId === (string) $addon->id ? 'selected' : '' }}>
                    {{ $addon->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 new-addon-fields {{ $isNewMode ? '' : 'd-none' }}">
        <div class="row g-1">
            <div class="col-7">
                <input type="text"
                       name="items[{{ $index }}][new_name]"
                       class="form-control template-new-name"
                       placeholder="{{ translate('Addon name') }}"
                       value="{{ $newName }}"
                       maxlength="255">
            </div>
            <div class="col-5">
                <input type="number"
                       step="any"
                       min="0"
                       name="items[{{ $index }}][new_price]"
                       class="form-control template-new-price"
                       placeholder="{{ translate('Price') }}"
                       value="{{ $newPrice }}">
            </div>
        </div>
    </div>
    <div class="col-md-1">
        <input type="number" min="0" class="form-control" name="items[{{ $index }}][sort_order]" value="{{ $sortOrder }}" placeholder="{{ translate('Sort') }}">
    </div>
    <div class="col-md-2 d-flex align-items-center">
        <label class="template-toggle-label">
            <input type="checkbox" name="items[{{ $index }}][is_default]" {{ $isDefault ? 'checked' : '' }}> {{ translate('Default') }}
        </label>
    </div>
    <div class="col-md-1">
        <div class="template-item-actions">
            <label class="template-toggle-label">
                <input type="checkbox" name="items[{{ $index }}][is_active]" {{ $isActive ? 'checked' : '' }}> {{ translate('Active') }}
            </label>
            <button type="button" class="btn btn-danger btn-sm remove-template-item-row"><i class="tio-delete"></i></button>
        </div>
    </div>
</div>
