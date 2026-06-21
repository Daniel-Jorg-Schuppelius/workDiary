@php
    /** @var \App\Models\Software $software */
    $action = $software->exists
        ? route('software.update', $software)
        : route('software.store');
@endphp

<x-modal
    :title="$software->exists ? __('Software bearbeiten') : __('Software anlegen')"
    :eyebrow="__('Software')"
    icon="apps"
    tone="primary"
    :action="$action"
    :method="$software->exists ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <x-slot:headerActions>
        <x-dialog-status-controls :active="(bool) ($software->is_active ?? true)" />
    </x-slot:headerActions>

    @include('software._form_body', ['software' => $software, 'skipStatusControls' => true])

    @if ($software->exists)
        <x-slot:footerExtra>
            <x-action-form :action="route('software.destroy', $software)" method="DELETE"
                  :confirm="__('Software wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
