{{-- Variablen: $type (ShiftType|null), $isEdit --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('shift-types.update', $type)
        : route('shift-types.store');
@endphp

<x-modal
    :title="$isEdit ? __('Schichttyp bearbeiten') : __('Schichttyp anlegen')"
    :eyebrow="__('Schichttypen')"
    icon="sync"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-slot:headerActions>
        <x-dialog-status-controls
            :active="$type?->is_active ?? true"
            :color="$type?->color ?? '#3b82f6'" />
    </x-slot:headerActions>

    @include('shift-types._form', ['type' => $type, 'skipStatusControls' => true])

    @if ($isEdit)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('shift-types.destroy', $type) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Schichttyp löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
