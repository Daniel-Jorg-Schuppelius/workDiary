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
        <x-input-field name="installed_version" :label="__('isms.field.installed_version')" maxlength="64" :value="old('installed_version', $installation?->installed_version)" placeholder="{{ $product->product_version }}" />
        <x-input-field name="asset_ref" :label="__('isms.field.asset_ref')" maxlength="180" :value="old('asset_ref', $installation?->asset_ref)" placeholder="{{ __('isms.hint.installation_asset_ref') }}" />
        <x-input-field name="location" :label="__('isms.field.location')" maxlength="180" span="2" :value="old('location', $installation?->location)" />
        <x-textarea-field name="notes" :label="__('isms.field.notes')" rows="2" maxlength="10000" span="2" :value="old('notes', $installation?->notes)" />
    </x-form-group>
</x-modal>
