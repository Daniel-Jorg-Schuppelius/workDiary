{{--
  Created on   : Fri Jul 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erwartet: $ticket (nullable), $priorityOptions, $isDialog
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
        <x-input-field name="customer_id" type="number" min="1" :label="__('Kunden-ID')"
                       :value="old('customer_id', $ticket?->customer_id)" />
        <x-input-field name="asset_id" type="number" min="1" :label="__('Asset-ID')"
                       :value="old('asset_id', $ticket?->asset_id)" />
        <x-input-field name="project_id" type="number" min="1" :label="__('Projekt-ID')"
                       :value="old('project_id', $ticket?->project_id)" />
    </x-form-group>
</x-modal>
