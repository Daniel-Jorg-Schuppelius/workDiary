{{--
  Created on   : Fri Jul 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erwartet: $ticket (nullable), $priorityOptions, $isDialog, $customers,
  $projects, $assets, $services (leer ohne Modul Service Desk)
--}}
@php
    $isDialog = $isDialog ?? false;
    $ticket = $ticket ?? null;
@endphp

<x-modal
    :title="__('Ticket anlegen')"
    :eyebrow="__('Service-Ticket')"
    icon="support_agent"
    tone="primary"
    :action="route('service-tickets.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Ticket anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('service-tickets.create') }}">
    @endif

    <x-form-group :legend="__('Ticketdaten')" icon="support_agent" tone="primary">
        <x-input-field name="title" :label="__('Titel')" required maxlength="200"
                       :value="old('title', $ticket?->title)" />

        <x-textarea-field name="description" :label="__('Beschreibung')" rows="4"
                          :value="old('description', $ticket?->description)" />

        <x-select-field name="priority" :label="__('Priorität')" required>
            @foreach ($priorityOptions as $val => $label)
                <option value="{{ $val }}" @selected(old('priority', 'normal') === $val)>{{ $label }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Verknüpfungen (optional)')" icon="link" tone="ghost" cols="2">
        <x-select-field name="customer_id" :label="__('Kunde')">
            <option value="">{{ __('helpdesk.form.noCustomer') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id') === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </x-select-field>
        <x-project-select :label="__('Projekt')" :placeholder="__('helpdesk.form.noProject')"
            :projects="$projects" :selected="(string) old('project_id', '')"
            data-depends-on="customer_id" :data-parent="true" />
        <x-select-field name="asset_id" :label="__('Asset')" data-depends-on="customer_id" span="2">
            <option value="">{{ __('helpdesk.form.noAsset') }}</option>
            @foreach ($assets as $a)
                <option value="{{ $a->sqid }}" data-parent="{{ $a->customer?->sqid }}" @selected((string) old('asset_id') === $a->sqid)>{{ $a->asset_no ? $a->asset_no . ' · ' : '' }}{{ $a->name }}</option>
            @endforeach
        </x-select-field>
        @if ($services->isNotEmpty())
            @include('service-tickets._service_fields', ['selectedService' => old('business_service_id'), 'selectedOffering' => old('service_offering_id')])
        @endif
    </x-form-group>
</x-modal>
