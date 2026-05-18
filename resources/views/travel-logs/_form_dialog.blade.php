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
            <form method="POST" action="{{ route('travel-logs.destroy', $log) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Fahrt wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
