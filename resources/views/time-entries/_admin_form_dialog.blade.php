{{-- Dialog wrapper for AdminTimeEntry create/edit --}}
@php
    /** @var \App\Models\TimeEntry|null $entry */
    $isEdit = (bool) $entry;
    $action = $isEdit
        ? route('admin-time-entries.update', $entry)
        : route('admin-time-entries.store');
@endphp
<x-modal
    :title="$isEdit ? __('Verwaltungszeit bearbeiten') : __('Verwaltungszeit erfassen')"
    :eyebrow="$date"
    icon="access_time"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Erfassen')"
>
    @include('time-entries._admin_form_body')

    @if ($isEdit)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('admin-time-entries.destroy', $entry) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Löschen') }}</button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
