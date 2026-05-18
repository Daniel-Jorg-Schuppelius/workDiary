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
    @include('admin.entry-types._form_body')

    @if ($isEdit)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('admin.entry-types.destroy', $entryType) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Eintragstyp wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Löschen') }}</button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
