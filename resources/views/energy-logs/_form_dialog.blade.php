{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $log (Model|null), $vehicles, $types, $fuelKinds, $chargerTypes, $defaultVehicleId --}}
@php
    $action = $log
        ? route('energy-logs.update', $log)
        : route('energy-logs.store');
@endphp

<x-modal
    :title="$log ? __('Eintrag bearbeiten') : __('Neuer Tank-/Ladeeintrag')"
    :eyebrow="__('Tanken & Laden')"
    icon="local_gas_station"
    tone="primary"
    :action="$action"
    :method="$log ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    @include('energy-logs._form_body', ['log' => $log ?? null])

    @if ($log)
        <x-slot:footerExtra>
            <x-action-form :action="route('energy-logs.destroy', $log)" method="DELETE"
                  :confirm="__('Eintrag wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
