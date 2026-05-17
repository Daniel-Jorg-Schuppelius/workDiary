{{-- Variablen: $log (Model|null), $vehicles, $types, $fuelKinds, $chargerTypes, $defaultVehicleId --}}
@php
    $action = $log
        ? route('energy-logs.update', $log)
        : route('energy-logs.store');
@endphp

<x-modal
    :title="$log ? __('Eintrag bearbeiten') : __('Neuer Tank-/Ladeeintrag')"
    :eyebrow="__('Tanken & Laden')"
    icon="local_gas_station"
    tone="primary"
    :action="$action"
    :method="$log ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    @include('energy-logs._form_body', ['log' => $log ?? null])

    @if ($log)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('energy-logs.destroy', $log) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Eintrag wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-error btn-outline btn-sm gap-2">
                    <x-icon name="delete" /> {{ __('Löschen') }}
                </button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
