{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $vehicle (Model|null), $users, $types, $propulsions, $ownerships --}}
@php
    $action = $vehicle
        ? route('vehicles.update', $vehicle)
        : route('vehicles.store');
@endphp

<x-modal
    :title="$vehicle ? __('Fahrzeug bearbeiten') : __('Neues Fahrzeug')"
    :eyebrow="__('Fuhrpark')"
    icon="directions_car"
    tone="primary"
    :action="$action"
    :method="$vehicle ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$vehicle ? __('Speichern') : __('Anlegen')">

    <div x-data="reveal(@js(old('ownership', $vehicle?->ownership?->value ?? 'owned')))" class="space-y-4">
        @include('vehicles._form_body', ['vehicle' => $vehicle ?? null])
    </div>

    @if ($vehicle && ! $vehicle->archived_at)
        <x-slot:footerExtra>
            <x-action-form :action="route('vehicles.destroy', $vehicle)" method="DELETE"
                  :confirm="__('Fahrzeug wirklich archivieren?')"
                  :confirm-label="__('Archivieren')">
                <x-icon-btn icon="archive" tone="error" size="sm" type="submit" show-label>{{ __('Archivieren') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
