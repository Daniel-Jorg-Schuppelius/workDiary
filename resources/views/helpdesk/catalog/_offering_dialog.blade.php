{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _offering_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $offering, $isEdit, $services, $preselectedService --}}
@php
    /** @var \App\Models\ServiceOffering $offering */
    /** @var bool $isEdit */
    $action = $isEdit ? route('servicedesk.catalog.offerings.update', $offering) : route('servicedesk.catalog.offerings.store');
@endphp

<x-modal
    :title="$isEdit ? __('Serviceangebot bearbeiten') : __('Neues Serviceangebot')"
    :eyebrow="__('Servicekatalog')"
    icon="widgets"
    tone="primary"
    size="md"
    :action="$action"
    :method="$isEdit ? 'PATCH' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Serviceangebot')" icon="widgets" tone="primary" cols="2">
        <x-select-field name="business_service_id" :label="__('Fachdienst')" required span="2">
            <option value="">—</option>
            @foreach ($services as $service)
                <option value="{{ $service->sqid }}"
                        @selected(old('business_service_id', (int) ($preselectedService ?? 0) === (int) $service->id ? $service->sqid : null) === $service->sqid)>
                    {{ $service->name }}
                </option>
            @endforeach
        </x-select-field>

        <x-input-field name="name" :label="__('Name')" required maxlength="150" span="2" :value="old('name', $offering->name)" />
        <x-input-field name="description" :label="__('Beschreibung')" maxlength="500" span="2" :value="old('description', $offering->description)" />
        <x-checkbox-field name="active" :label="__('Aktiv')" :checked="(bool) old('active', $offering->active)" />
    </x-form-group>
</x-modal>
