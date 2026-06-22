@php
    /** @var \App\Models\Permit $permit */
    $action = $permit->exists
        ? route('permits.update', $permit)
        : route('permits.store');
    $evidence = $permit->exists ? $permit->evidence() : null;
@endphp

<x-modal
    :title="$permit->exists ? __('permit.edit') : __('permit.create')"
    :eyebrow="__('permit.label')"
    icon="verified"
    tone="primary"
    :action="$action"
    :method="$permit->exists ? 'PUT' : 'POST'"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    @include('permits._form_body', ['permit' => $permit])

    @if ($permit->exists)
        <x-slot:footerExtra>
            @if ($evidence)
                <x-action-form :action="route('attachments.destroy', $evidence)" method="DELETE"
                      :confirm="__('permit.evidence.remove_confirm')" :confirm-label="__('Löschen')">
                    <x-icon-btn icon="attachment" tone="ghost" size="sm" type="submit" show-label>{{ __('permit.evidence.remove') }}</x-icon-btn>
                </x-action-form>
            @endif
            <x-action-form :action="route('permits.destroy', $permit)" method="DELETE"
                  :confirm="__('permit.delete_confirm')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
