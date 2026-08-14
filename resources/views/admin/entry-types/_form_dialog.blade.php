{{--
  Created on   : Mon May 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog wrapper for EntryType create/edit --}}
@php
    /** @var \App\Models\EntryType $entryType */
    $isEdit = $entryType?->exists ?? false;
@endphp
<x-modal
    :title="$isEdit ? __('Eintragstyp bearbeiten') : __('Eintragstyp anlegen')"
    :eyebrow="$isEdit ? $entryType->label : null"
    icon="category"
    tone="primary"
    :action="$isEdit ? route('admin.entry-types.update', $entryType) : route('admin.entry-types.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')"
>
    <x-slot:headerActions>
        <x-dialog-status-controls
            :active="$entryType?->is_active ?? true" />
    </x-slot:headerActions>

    @include('admin.entry-types._form_body', ['skipStatusControls' => true])

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('admin.entry-types.destroy', $entryType)" method="DELETE"
                  :confirm="__('Eintragstyp wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
