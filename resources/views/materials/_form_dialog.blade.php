{{-- Variablen: $material (Material, optional ->exists) --}}
@php
    $action = $material->exists
        ? route('materials.update', $material)
        : route('materials.store');
@endphp

<x-modal
    :title="$material->exists ? __('Material bearbeiten') : __('Material anlegen')"
    :eyebrow="__('Materialien')"
    icon="category"
    tone="primary"
    :action="$action"
    :method="$material->exists ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    @include('materials._form_body', ['material' => $material])

    @if ($material->exists)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('materials.destroy', $material) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Material wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
