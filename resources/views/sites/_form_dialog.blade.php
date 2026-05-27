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
            <form method="POST" action="{{ route('sites.destroy', $site) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Standort wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Standort löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
