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
            <form method="POST" action="{{ route('per-diem-trips.destroy', $trip) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Reise wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
