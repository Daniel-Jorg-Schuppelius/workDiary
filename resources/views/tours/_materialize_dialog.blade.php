{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _materialize_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $tour, $requiresOdometer (Fahrzeug im Fahrtenbuch-Modus) --}}
<x-modal
    :title="__('Als Fahrten übernehmen')"
    :eyebrow="__('Tour') . ' ' . ($tour->name ?? ('#' . $tour->id))"
    icon="directions_car"
    tone="primary"
    :action="route('tours.materialize', $tour)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Übernehmen')">

    <p class="text-sm">
        {{ __('Je Abschnitt der Tour entsteht eine Fahrt im Fahrtenbuch: vom Start zum ersten Stopp, zwischen den Stopps und zurück zum Ziel. Straßenkilometer kommen aus der berechneten Route; ohne Route gilt die Luftlinie und die Fahrt ist so gekennzeichnet.') }}
    </p>

    @if ($tour->vehicle)
        <p class="text-sm text-muted">
            {{ __('Fahrzeug') }}: {{ $tour->vehicle->license_plate }} {{ $tour->vehicle->label }}
            @if ($requiresOdometer)
                · {{ __('Fahrtenbuch-Modus') }}
            @endif
        </p>
    @endif

    @if ($requiresOdometer)
        <x-form-group :legend="__('Tachostand')" icon="speed" tone="warning" cols="1">
            <x-input-field name="odometer_start_km" type="number" min="0" step="1" required
                           :label="__('Tachostand Beginn (km)')"
                           :value="old('odometer_start_km', $tour->vehicle?->odometer_km)" />
            <p class="text-xs text-muted">
                {{ __('Die Stände der Fahrten folgen lückenlos aufeinander; der Tacho zählt ganze Kilometer, die Rundung läuft über die Tour mit.') }}
            </p>
        </x-form-group>
    @endif

    <x-validation-errors />
</x-modal>
