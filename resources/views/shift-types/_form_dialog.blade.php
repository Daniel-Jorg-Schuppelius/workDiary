{{-- Variablen: $type (ShiftType|null), $isEdit --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('shift-types.update', $type)
        : route('shift-types.store');
@endphp

<x-dialog
    :title="$isEdit ? __('Schichttyp bearbeiten') : __('Schichttyp anlegen')"
    :eyebrow="__('Schichttypen')"
    icon="🔄"
    tone="primary">

    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($isEdit) @method('PUT') @endif

        @include('shift-types._form', ['type' => $type])

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-primary btn-sm">{{ $isEdit ? __('Speichern') : __('Anlegen') }}</button>
            <button type="button" class="btn btn-ghost btn-sm" data-entry-modal-close>{{ __('Abbrechen') }}</button>
        </div>
    </form>

    @if ($isEdit)
        <form method="POST" action="{{ route('shift-types.destroy', $type) }}" class="mt-3"
              onsubmit="return confirm('{{ __('Wirklich löschen?') }}')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-error btn-outline btn-sm">{{ __('Schichttyp löschen') }}</button>
        </form>
    @endif
</x-dialog>
