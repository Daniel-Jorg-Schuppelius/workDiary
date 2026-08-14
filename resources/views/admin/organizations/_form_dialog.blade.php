{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog wrapper for Organization create/edit --}}
@php
    $isEdit = $organization?->exists ?? false;
@endphp
<x-modal
    :title="$isEdit ? __('Organisation bearbeiten') : __('Organisation anlegen')"
    :eyebrow="$isEdit ? $organization->name : null"
    icon="apartment"
    tone="primary"
    :action="$isEdit ? route('admin.organizations.update', $organization) : route('admin.organizations.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')"
>
    <x-slot:headerActions>
        <x-dialog-status-controls
            :active="$organization?->is_active ?? true" />
    </x-slot:headerActions>

    @include('admin.organizations._form_body', ['skipStatusControls' => true])

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('admin.organizations.destroy', $organization)" method="DELETE"
                  :confirm="__('Organisation wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
