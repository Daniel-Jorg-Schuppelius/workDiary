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
            <form method="POST" action="{{ route('floors.destroy', $floor) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Geschoss wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Geschoss löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
