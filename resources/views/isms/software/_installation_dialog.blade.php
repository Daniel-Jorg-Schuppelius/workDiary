{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _installation_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Installation (in #entry-modal geladen).
  Variablen: $product (IsmsSoftwareProduct), $installation (IsmsSoftwareInstallation|null)
--}}
@php
    $isEdit = $installation !== null;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_installation') : __('isms.action.create_installation')"
    :eyebrow="$product->name"
    icon="dns"
    tone="primary"
    size="md"
    :action="$isEdit ? route('isms.software.installations.update', $installation) : route('isms.software.installations.store', $product)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_installation')">

    <x-form-group :legend="__('isms.group.installation')" icon="dns" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.installed_version') }}</span>
            <input type="text" name="installed_version" maxlength="64"
                   class="input input-bordered w-full"
                   value="{{ old('installed_version', $installation?->installed_version) }}"
                   placeholder="{{ $product->product_version }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.asset_ref') }}</span>
            <input type="text" name="asset_ref" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('asset_ref', $installation?->asset_ref) }}"
                   placeholder="{{ __('isms.hint.installation_asset_ref') }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.location') }}</span>
            <input type="text" name="location" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('location', $installation?->location) }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.notes') }}</span>
            <textarea name="notes" rows="2" maxlength="10000"
                      class="textarea textarea-bordered w-full">{{ old('notes', $installation?->notes) }}</textarea>
        </label>
    </x-form-group>
</x-modal>
