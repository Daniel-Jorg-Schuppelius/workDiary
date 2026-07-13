{{-- Variablen: $service, $isEdit --}}
@php
    /** @var \App\Models\BusinessService $service */
    /** @var bool $isEdit */
    $action = $isEdit ? route('servicedesk.catalog.services.update', $service) : route('servicedesk.catalog.services.store');
@endphp

<x-modal
    :title="$isEdit ? __('Fachdienst bearbeiten') : __('Neuer Fachdienst')"
    :eyebrow="__('Servicekatalog')"
    icon="category"
    tone="primary"
    size="md"
    :action="$action"
    :method="$isEdit ? 'PATCH' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Fachdienst')" icon="category" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Name')" required maxlength="150" span="2" :value="old('name', $service->name)" />
        <x-input-field name="description" :label="__('Beschreibung')" maxlength="500" span="2" :value="old('description', $service->description)" />
        <x-checkbox-field name="active" :label="__('Aktiv')" :checked="(bool) old('active', $service->active)"
                          :hint="__('Inaktive Fachdienste erscheinen nicht im bestellbaren Katalog.')" />
    </x-form-group>
</x-modal>
