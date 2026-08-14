{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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

    <x-slot:headerActions>
        <x-dialog-status-controls
            :active="$material->is_active ?? true" />
    </x-slot:headerActions>

    @include('materials._form_body', ['material' => $material, 'skipStatusControls' => true])

    @if ($material->exists)
        <x-slot:footerExtra>
            <x-action-form :action="route('materials.destroy', $material)" method="DELETE"
                  :confirm="__('Material wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
