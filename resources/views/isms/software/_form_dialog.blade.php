{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Softwareprodukt (in #entry-modal geladen).
  Variablen: $product (IsmsSoftwareProduct|null), $owners (Collection id/name)
--}}
@php
    $isEdit = $product !== null;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_software') : __('isms.action.create_software')"
    :eyebrow="__('isms.title.software')"
    icon="apps"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('isms.software.update', $product) : route('isms.software.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_software')">

    <x-form-group :legend="__('isms.group.software')" icon="apps" tone="primary" cols="2">
        <x-input-field name="name" :label="__('isms.field.name')" required minlength="2" maxlength="180" :value="old('name', $product?->name)" />
        <x-input-field name="vendor" :label="__('isms.field.vendor')" maxlength="120" :value="old('vendor', $product?->vendor)" />
        <x-input-field name="product_version" :label="__('isms.field.product_version')" maxlength="64" :value="old('product_version', $product?->product_version)" placeholder="{{ __('isms.hint.product_version') }}" />
        <x-select-field name="category" :label="__('isms.field.category')">
                <option value="">—</option>
                @foreach (\App\Enums\Isms\SoftwareCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $product?->category?->value) === $category->value)>{{ $category->label() }}</option>
                @endforeach
        </x-select-field>
        <x-textarea-field name="notes" :label="__('isms.field.notes')" rows="3" maxlength="10000" span="2" :value="old('notes', $product?->notes)" />
    </x-form-group>

    <x-form-group :legend="__('isms.group.support')" icon="support_agent" tone="warning" cols="2">
        <x-select-field name="support_status" :label="__('isms.field.support_status')" required>
                @foreach (\App\Enums\Isms\SupportStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('support_status', $product?->support_status?->value ?? 'unknown') === $status->value)>{{ $status->label() }}</option>
                @endforeach
        </x-select-field>
        <x-input-field name="eol_on" type="date" :label="__('isms.field.eol_on')" :value="old('eol_on', $product?->eol_on?->toDateString())" :hint="__('isms.hint.eol_on')" />
        <x-select-field name="owner_user_id" :label="__('isms.field.owner')" span="2">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_user_id', $product?->owner_user_id) === (string) $owner->id)>{{ $owner->name }}</option>
                @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>
