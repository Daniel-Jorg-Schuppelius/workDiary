{{-- Variablen: $qualification (Model|null), $isEdit --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('qualifications.update', $qualification)
        : route('qualifications.store');
@endphp

<x-modal
    :title="$isEdit ? __('Qualifikation bearbeiten') : __('Qualifikation anlegen')"
    :eyebrow="__('Qualifikationen')"
    icon="school"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-slot:headerActions>
        <x-dialog-status-controls
            :active="$qualification?->is_active ?? true" />
    </x-slot:headerActions>

    @include('qualifications._form', ['qualification' => $qualification ?? null, 'skipStatusControls' => true])

    <x-validation-errors />

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('qualifications.destroy', $qualification)" method="DELETE"
                  :confirm="__('Wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Qualifikation löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
