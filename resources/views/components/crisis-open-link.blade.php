{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : crisis-open-link.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einstieg „Krise eröffnen“ an Sicherheitsvorfall, Datenschutzvorfall, Sicherheitsereignis und
     Major-Incident-Ticket (MVP-1605). :from nennt die Art (isms_incident|privacy_incident|safety_event|
     service_ticket), :source die Sqid; der Dialog belegt Titel, Kategorie und Auslöser vor und verknüpft
     den Vorfall. Sichtbar nur mit aktivem Modul und Anlagerecht. --}}
@props(['from', 'source', 'size' => 'sm', 'iconOnly' => false])
@if (app(\App\Services\Navigation\NavGate::class)->allows('crisis.create'))
    @can('create', \App\Models\Crisis\CrisisCase::class)
        @if ($iconOnly)
            <x-icon-btn icon="emergency_home" tone="outline" :size="$size" data-entry-modal-trigger
                        :href="route('crisis.create', ['from' => $from, 'source' => $source])"
                        :label="__('crisis.open.action')" {{ $attributes }} />
        @else
            <x-icon-btn icon="emergency_home" tone="outline" :size="$size" data-entry-modal-trigger
                        :href="route('crisis.create', ['from' => $from, 'source' => $source])" show-label {{ $attributes }}>{{ __('crisis.open.action') }}</x-icon-btn>
        @endif
    @endcan
@endif
