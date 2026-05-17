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
    @include('admin.organizations._form_body')

    @if ($isEdit)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('admin.organizations.destroy', $organization) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Organisation wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Löschen') }}</button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
