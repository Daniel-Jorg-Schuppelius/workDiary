{{--
  Created on   : Wed May 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $floor (Model|null), $buildings --}}
@php
    $action = $floor
        ? route('floors.update', $floor)
        : route('floors.store');
@endphp

<x-modal
    :title="$floor ? __('Geschoss bearbeiten') : __('Neues Geschoss')"
    :eyebrow="__('Liegenschaften')"
    icon="layers"
    tone="primary"
    :action="$action"
    :method="$floor ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$floor ? __('Speichern') : __('Anlegen')">

    @include('floors._form_body', ['floor' => $floor ?? null, 'buildings' => $buildings])

    @if ($floor)
        <x-slot:footerExtra>
            <x-action-form :action="route('floors.destroy', $floor)" method="DELETE"
                  :confirm="__('Geschoss wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Geschoss löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
