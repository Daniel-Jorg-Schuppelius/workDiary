{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _service_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Buchbare Leistungsart anlegen bzw. bearbeiten (in #entry-modal geladen). Variablen: $service (null = neu), $sites, $qualifications --}}
<x-modal
    :title="$service === null ? __('Leistungsart anlegen') : __('appointment.service.edit_title')"
    :eyebrow="__('Terminanfragen')"
    icon="event_available"
    tone="primary"
    :action="$service === null ? route('appointments.services.store') : route('appointments.services.update', $service)"
    :method="$service === null ? 'POST' : 'PUT'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$service === null ? __('Anlegen') : __('Speichern')">

    <x-form-group :legend="__('Leistung')" icon="design_services" tone="primary" cols="1">
        <x-input-field name="title" :label="__('Bezeichnung')" :value="old('title', $service?->title)" required maxlength="160"
                       :placeholder="__('Leistungsart (z. B. Wartungstermin)')" />
        <x-input-field name="description" :label="__('Beschreibung (im Portal sichtbar)')" :value="old('description', $service?->description)" maxlength="500" />
    </x-form-group>

    <x-form-group :legend="__('Zeiten')" icon="schedule" tone="ghost" cols="2">
        <x-input-field type="number" name="duration_minutes" :label="__('Dauer (min)')" :value="old('duration_minutes', $service?->duration_minutes ?? 60)" required min="15" max="480" />
        <x-input-field type="number" name="buffer_minutes" :label="__('Puffer (min)')" :value="old('buffer_minutes', $service?->buffer_minutes ?? 15)" required min="0" max="120"
                       :hint="__('Abstand zum nächsten Einsatz.')" />
        <x-input-field type="number" name="lead_time_hours" :label="__('Vorlauf (h)')" :value="old('lead_time_hours', $service?->lead_time_hours ?? 24)" required min="0" max="720"
                       :hint="__('Frühester Termin ab Anfrage.')" />
        <x-input-field type="number" name="cancel_hours" :label="__('Stornofrist (h)')" :value="old('cancel_hours', $service?->cancel_hours ?? 24)" required min="0" max="720"
                       :hint="__('Bis so lange vorher darf der Kunde stornieren.')" />
    </x-form-group>

    <x-form-group :legend="__('Einschränkungen')" icon="rule" tone="ghost" cols="2">
        <x-select-field name="qualification" :label="__('Benötigte Qualifikation')"
                        :hint="__('Angeboten werden nur Fenster von Kräften mit gültiger Qualifikation.')">
            <option value="">{{ __('— keine Qualifikation nötig —') }}</option>
            @foreach ($qualifications as $qualification)
                <option value="{{ $qualification->sqid }}" @selected(old('qualification', $service?->requiredQualification?->sqid) === $qualification->sqid)>{{ $qualification->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="site" :label="__('Objekt / Standort')" :hint="__('appointment.service.site_hint')">
            <option value="">{{ __('— kein Standortbezug —') }}</option>
            @foreach ($sites as $site)
                <option value="{{ $site->sqid }}" @selected(old('site', $service?->site?->sqid) === $site->sqid)>{{ $site->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>
