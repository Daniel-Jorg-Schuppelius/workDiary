{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $log (Model|null), $date, $projects, $customers, $vehicles, $rates --}}
@php
    $action = $log
        ? route('travel-logs.update', $log)
        : route('travel-logs.store');
@endphp

<x-modal
    :title="$log ? __('Fahrt bearbeiten') : __('Neue Fahrt erfassen')"
    :eyebrow="__('Fahrtenbuch')"
    icon="directions_car"
    tone="primary"
    :action="$action"
    :method="$log ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$log ? __('Speichern') : __('Erfassen')">

    @include('travel-logs._form_body', ['log' => $log ?? null])

    @if ($log)
        <x-slot:footerExtra>
            <x-action-form :action="route('travel-logs.destroy', $log)" method="DELETE"
                  :confirm="__('Fahrt wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
