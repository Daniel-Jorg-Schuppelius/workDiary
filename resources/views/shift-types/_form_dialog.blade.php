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

    @include('shift-types._form', ['type' => $type])

    @if ($isEdit)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('shift-types.destroy', $type) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-error btn-outline btn-sm gap-2">
                    <x-icon name="delete" /> {{ __('Schichttyp löschen') }}
                </button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
