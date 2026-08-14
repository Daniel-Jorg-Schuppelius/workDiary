{{--
  Created on   : Wed May 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $site (Model|null), $customers --}}
@php
    $action = $site
        ? route('sites.update', $site)
        : route('sites.store');
@endphp

<x-modal
    :title="$site ? __('Standort bearbeiten') : __('Neuer Standort')"
    :eyebrow="__('Liegenschaften')"
    icon="location_on"
    tone="primary"
    :action="$action"
    :method="$site ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$site ? __('Speichern') : __('Anlegen')">

    @include('sites._form_body', ['site' => $site ?? null, 'customers' => $customers])

    @if ($site)
        <x-slot:footerExtra>
            <x-action-form :action="route('sites.destroy', $site)" method="DELETE"
                  :confirm="__('Standort wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Standort löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
