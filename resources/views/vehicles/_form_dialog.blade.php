{{-- Variablen: $vehicle (Model|null), $users, $types, $propulsions, $ownerships --}}
@php
    $action = $vehicle
        ? route('vehicles.update', $vehicle)
        : route('vehicles.store');
@endphp

<x-modal
    :title="$vehicle ? __('Fahrzeug bearbeiten') : __('Neues Fahrzeug')"
    :eyebrow="__('Fuhrpark')"
    icon="directions_car"
    tone="primary"
    :action="$action"
    :method="$vehicle ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$vehicle ? __('Speichern') : __('Anlegen')">

    <div x-data="{ ownership: '{{ old('ownership', $vehicle?->ownership ?? 'owned') }}' }" class="space-y-4">
        @include('vehicles._form_body', ['vehicle' => $vehicle ?? null])
    </div>

    @if ($vehicle && ! $vehicle->archived_at)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Fahrzeug wirklich archivieren?') }}"
                  data-confirm-label="{{ __('Archivieren') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-error btn-outline btn-sm gap-2">
                    <x-icon name="delete" /> {{ __('Archivieren') }}
                </button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
