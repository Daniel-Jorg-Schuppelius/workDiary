{{--
  Created on   : Wed May 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $building (Model|null), $sites --}}
@php
    $action = $building
        ? route('buildings.update', $building)
        : route('buildings.store');
@endphp

<x-modal
    :title="$building ? __('Gebäude bearbeiten') : __('Neues Gebäude')"
    :eyebrow="__('Liegenschaften')"
    icon="apartment"
    tone="primary"
    :action="$action"
    :method="$building ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$building ? __('Speichern') : __('Anlegen')">

    @include('buildings._form_body', ['building' => $building ?? null, 'sites' => $sites])

    @if ($building)
        <x-slot:footerExtra>
            <x-action-form :action="route('buildings.destroy', $building)"
                  method="DELETE"
                  :confirm="__('Gebäude wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Gebäude löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
