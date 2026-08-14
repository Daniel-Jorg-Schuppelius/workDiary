{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $trip (Model|null), $date, $projects, $customers, $travelLogs, $countries --}}
@php
    $action = $trip
        ? route('per-diem-trips.update', $trip)
        : route('per-diem-trips.store');
@endphp

<x-modal
    :title="$trip ? __('Reise bearbeiten') : __('Neue Reise erfassen')"
    :eyebrow="__('Verpflegungspauschalen')"
    icon="restaurant_menu"
    tone="primary"
    :action="$action"
    :method="$trip ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$trip ? __('Speichern') : __('Erfassen')">

    @include('per-diem-trips._form_body', ['trip' => $trip ?? null])

    @if ($trip)
        <x-slot:footerExtra>
            <x-action-form :action="route('per-diem-trips.destroy', $trip)" method="DELETE"
                  :confirm="__('Reise wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
