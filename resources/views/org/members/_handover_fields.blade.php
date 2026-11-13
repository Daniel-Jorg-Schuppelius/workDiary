{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _handover_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Übergabe der Module (Erweiterungspunkt OffboardingHandover): je Bereich eine
     Nachfolge oder keine. Gemeinsam für Austritt (Feature 126) und endgültige
     Übergabe ohne Austritt (Feature 202, MVP-1648).
     Variablen: $handovers (list<array{key, label}>), $candidates (Personen), $legend, $hint --}}
<x-form-group :legend="$legend" icon="move_up" tone="primary">
    <p class="text-sm text-muted">{{ $hint }}</p>
    @foreach ($handovers as $handover)
        <x-select-field :name="'handovers[' . $handover['key'] . ']'" :id="'handover-' . $handover['key']" :label="$handover['label']">
            <option value="">{{ __('hr.offboarding.no_handover') }}</option>
            @foreach ($candidates as $candidate)
                <option value="{{ $candidate->sqid }}">{{ $candidate->name }}</option>
            @endforeach
        </x-select-field>
    @endforeach
</x-form-group>
