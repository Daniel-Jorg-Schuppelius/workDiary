{{--
  Created on   : Tue Jun 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $geofence (Model|null), $customers, $sites, $projects --}}
@php
    $action = $geofence
        ? route('geofences.update', $geofence)
        : route('geofences.store');
@endphp

<x-modal
    :title="$geofence ? __('Geofence bearbeiten') : __('Neuer Geofence')"
    :eyebrow="__('Standorterfassung')"
    icon="pin_drop"
    tone="primary"
    :action="$action"
    :method="$geofence ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$geofence ? __('Speichern') : __('Anlegen')">

    @include('geofences._form_body', [
        'geofence' => $geofence ?? null,
        'customers' => $customers,
        'sites' => $sites,
        'projects' => $projects,
    ])

    @if ($geofence)
        <x-slot:footerExtra>
            <x-action-form :action="route('geofences.destroy', $geofence)" method="DELETE"
                  :confirm="__('Geofence wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Geofence löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
