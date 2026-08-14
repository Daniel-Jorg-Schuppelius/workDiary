{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
            <x-action-form :action="route('shift-types.destroy', $type)" method="DELETE"
                  :confirm="__('Wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Schichttyp löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
