@php($isEdit = isset($group))
<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="input-label">{{ translate('Template_Name') }}</label>
                <input type="text" class="form-control" name="name" maxlength="255" required
                       value="{{ old('name', $group->name ?? '') }}" placeholder="{{ translate('Extra Add-ons') }}">
            </div>
            <div class="col-md-6">
                <label class="input-label">{{ translate('Description') }}</label>
                <input type="text" class="form-control" name="description" maxlength="255"
                       value="{{ old('description', $group->description ?? '') }}" placeholder="{{ translate('Optional') }}">
            </div>
            <div class="col-md-4">
                <label class="input-label">{{ translate('Selection_Type') }}</label>
                <div class="resturant-type-group border p-2">
                    @php($selectedType = old('selection_type', $group->selection_type ?? 'multi'))
                    <label class="form-check form--check mr-3">
                        <input class="form-check-input selection-type-radio" type="radio" value="multi" name="selection_type" {{ $selectedType === 'multi' ? 'checked' : '' }}>
                        <span class="form-check-label">{{ translate('Multiple') }}</span>
                    </label>
                    <label class="form-check form--check">
                        <input class="form-check-input selection-type-radio" type="radio" value="single" name="selection_type" {{ $selectedType === 'single' ? 'checked' : '' }}>
                        <span class="form-check-label">{{ translate('Single') }}</span>
                    </label>
                </div>
            </div>
            <div class="col-md-2">
                <label class="input-label">{{ translate('Min') }}</label>
                <input type="number" class="form-control min-field" name="min" min="0"
                       value="{{ old('min', $group->min ?? 0) }}">
            </div>
            <div class="col-md-2">
                <label class="input-label">{{ translate('Max') }}</label>
                <input type="number" class="form-control max-field" name="max" min="0"
                       value="{{ old('max', $group->max ?? 0) }}">
            </div>
            <div class="col-md-2">
                <label class="input-label d-block">&nbsp;</label>
                <label class="d-flex gap-2 align-items-center m-0">
                    <input type="checkbox" name="is_required" {{ old('is_required', $group->is_required ?? false) ? 'checked' : '' }}>
                    <span>{{ translate('Required') }}</span>
                </label>
            </div>
            <div class="col-md-2">
                <label class="input-label d-block">&nbsp;</label>
                <label class="d-flex gap-2 align-items-center m-0">
                    <input type="checkbox" name="is_active" {{ old('is_active', $group->is_active ?? true) ? 'checked' : '' }}>
                    <span>{{ translate('Active') }}</span>
                </label>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">{{ translate('Modifier_Options') }}</h4>
        <button class="btn btn-outline-primary btn-sm" type="button" id="add-option-row">{{ translate('Add_New_Option') }}</button>
    </div>
    <div class="card-body">
        <div id="modifier-options-wrap">
            @php($oldOptions = old('options'))
            @php($existingOptions = $isEdit ? $group->options->toArray() : [])
            @php($optionsToRender = $oldOptions ?? $existingOptions)
            @forelse($optionsToRender as $index => $option)
                <div class="row g-2 option-row mb-2">
                    <div class="col-md-7">
                        <input type="text" class="form-control" name="options[{{ $index }}][name]" required maxlength="255"
                               value="{{ $option['name'] ?? '' }}" placeholder="{{ translate('Option_Name') }}">
                    </div>
                    <div class="col-md-4">
                        <input type="number" class="form-control" name="options[{{ $index }}][additional_price]" min="0" step="0.01"
                               value="{{ $option['additional_price'] ?? 0 }}" placeholder="{{ translate('Additional_price') }}">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-option-row"><i class="tio-add-to-trash"></i></button>
                    </div>
                </div>
            @empty
                <div class="row g-2 option-row mb-2">
                    <div class="col-md-7">
                        <input type="text" class="form-control" name="options[0][name]" required maxlength="255" placeholder="{{ translate('Option_Name') }}">
                    </div>
                    <div class="col-md-4">
                        <input type="number" class="form-control" name="options[0][additional_price]" min="0" step="0.01" value="0" placeholder="{{ translate('Additional_price') }}">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-option-row"><i class="tio-add-to-trash"></i></button>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>

@push('script_2')
    <script>
        "use strict";

        function updateOptionIndexes() {
            $('#modifier-options-wrap .option-row').each(function(index) {
                $(this).find('input[name*="[name]"]').attr('name', `options[${index}][name]`);
                $(this).find('input[name*="[additional_price]"]').attr('name', `options[${index}][additional_price]`);
            });
        }

        function toggleMinMaxRequirement() {
            const selectedType = $('.selection-type-radio:checked').val();
            const isMulti = selectedType === 'multi';
            $('.min-field, .max-field').prop('readonly', !isMulti);
            if (!isMulti) {
                $('.min-field').val(0);
                $('.max-field').val(0);
            }
        }

        $('#add-option-row').on('click', function() {
            const index = $('#modifier-options-wrap .option-row').length;
            const row = `
                <div class="row g-2 option-row mb-2">
                    <div class="col-md-7">
                        <input type="text" class="form-control" name="options[${index}][name]" required maxlength="255" placeholder="{{ translate('Option_Name') }}">
                    </div>
                    <div class="col-md-4">
                        <input type="number" class="form-control" name="options[${index}][additional_price]" min="0" step="0.01" value="0" placeholder="{{ translate('Additional_price') }}">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-option-row"><i class="tio-add-to-trash"></i></button>
                    </div>
                </div>
            `;
            $('#modifier-options-wrap').append(row);
        });

        $(document).on('click', '.remove-option-row', function() {
            if ($('#modifier-options-wrap .option-row').length <= 1) {
                return;
            }
            $(this).closest('.option-row').remove();
            updateOptionIndexes();
        });

        $(document).on('change', '.selection-type-radio', toggleMinMaxRequirement);
        toggleMinMaxRequirement();
    </script>
@endpush
