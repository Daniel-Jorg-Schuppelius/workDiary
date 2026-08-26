{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _major_incident.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Major-Incident-Widget (Feature 065, MVP-160): Ausrufen mit Pflicht-Lead
  (x-user-select, Sqid) sowie Aufheben; Beginn/Ende erscheinen als
  system_event in der Zeitlinie. Erwartet: $ticket (incidentLead geladen),
  $orgUsers, $canUpdate.
--}}

<x-card :title="__('Major Incident')" icon="emergency_home">
    @if ($ticket->is_major)
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <x-status-badge tone="error" size="md">{{ __('Major Incident aktiv') }}</x-status-badge>
            <span>{{ __('Leitung') }}: <strong>{{ $ticket->incidentLead?->name ?: '—' }}</strong></span>
            @if ($ticket->comm_rhythm)
                <span class="text-muted">· {{ __('Kommunikationsrhythmus') }}: {{ $ticket->comm_rhythm }}</span>
            @endif
        </div>
        @if (! empty($ticket->stakeholders))
            <p class="mt-2 text-sm text-base-content/70">
                {{ __('Stakeholder') }}: {{ implode(', ', (array) $ticket->stakeholders) }}
            </p>
        @endif
        @if ($canUpdate)
            <x-action-form :action="route('helpdesk.tickets.major.destroy', $ticket)"
                  method="DELETE"
                  class="mt-3"
                  :confirm="__('Major-Incident-Status aufheben?')"
                  :confirm-label="__('Aufheben')">
                <x-icon-btn icon="cancel" tone="error" size="sm" type="submit" show-label>{{ __('Aufheben') }}</x-icon-btn>
            </x-action-form>
        @endif
    @elseif ($canUpdate)
        <form method="POST" action="{{ route('helpdesk.tickets.major.store', $ticket) }}" class="space-y-2">
            @csrf
            <div>
                <label class="fieldset-label" for="incident-lead">{{ __('Leitung (Pflicht)') }}</label>
                <x-user-select name="incident_lead" id="incident-lead" :users="$orgUsers" value-key="sqid" required
                               class="select-sm" />
            </div>
            <div>
                <label class="fieldset-label" for="major-stakeholders">{{ __('Stakeholder (kommagetrennt)') }}</label>
                <input type="text" id="major-stakeholders" name="stakeholders" maxlength="1000"
                       value="{{ old('stakeholders') }}" class="input input-sm input-bordered w-full">
            </div>
            <div>
                <label class="fieldset-label" for="major-comm-rhythm">{{ __('Kommunikationsrhythmus') }}</label>
                <input type="text" id="major-comm-rhythm" name="comm_rhythm" maxlength="120"
                       value="{{ old('comm_rhythm') }}" class="input input-sm input-bordered w-full"
                       placeholder="{{ __('z. B. stündlich') }}">
            </div>
            <x-icon-btn icon="emergency_home" tone="error" size="sm" type="submit" show-label>{{ __('Major Incident ausrufen') }}</x-icon-btn>
        </form>
    @else
        <p class="text-sm text-muted">{{ __('Kein Major Incident.') }}</p>
    @endif
</x-card>
