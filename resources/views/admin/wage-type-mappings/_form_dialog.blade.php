{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Lohnart-Zuordnung anlegen/bearbeiten (A21 · MVP-019) --}}
@php
    /** @var \App\Models\WageTypeMapping $mapping */
    $isEdit = $mapping->exists;
@endphp
<x-modal
    :title="$isEdit ? __('wage_types.title.edit') : __('wage_types.title.create')"
    icon="badge"
    tone="primary"
    :action="$isEdit ? route('admin.wage-type-mappings.update', $mapping) : route('admin.wage-type-mappings.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('wage_types.action.save') : __('wage_types.action.create')"
>
    <x-form-group :legend="__('wage_types.field.basics')" icon="badge" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="wtm-profile">{{ __('wage_types.field.profile') }}</label>
            <select id="wtm-profile" name="profile" class="select select-bordered w-full" required>
                @foreach ($profiles as $key => $label)
                    <option value="{{ $key }}" @selected(old('profile', $mapping->profile) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('profile')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="wtm-wage-type">{{ __('wage_types.field.wage_type') }}</label>
            <select id="wtm-wage-type" name="wage_type" class="select select-bordered w-full font-mono" required>
                <option value="">{{ __('wage_types.field.choose') }}</option>
                <optgroup label="{{ __('wage_types.field.standard_types') }}">
                    @foreach ($wageTypes['standard'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('wage_type', $mapping->wage_type) === $value)>{{ $label }}</option>
                    @endforeach
                </optgroup>
                @if ($wageTypes['surcharge'] !== [])
                    <optgroup label="{{ __('wage_types.field.surcharge_types') }}">
                        @foreach ($wageTypes['surcharge'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('wage_type', $mapping->wage_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <p class="text-xs text-base-content/60">{{ __('wage_types.field.wage_type_help') }}</p>
            @error('wage_type')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="wtm-external-code">{{ __('wage_types.field.external_code') }}</label>
            <input id="wtm-external-code" type="text" name="external_code" required maxlength="20"
                   value="{{ old('external_code', $mapping->external_code) }}"
                   class="input input-bordered w-full font-mono" placeholder="1000">
            <p class="text-xs text-base-content/60">{{ __('wage_types.field.external_code_help') }}</p>
            @error('external_code')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('admin.wage-type-mappings.destroy', $mapping)"
                  method="DELETE"
                  :confirm="__('wage_types.action.delete_confirm')"
                  :confirm-label="__('wage_types.action.delete')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('wage_types.action.delete') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
