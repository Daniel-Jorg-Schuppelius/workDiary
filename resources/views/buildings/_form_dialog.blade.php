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
            <form method="POST" action="{{ route('buildings.destroy', $building) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Gebäude wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Gebäude löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
