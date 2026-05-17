{{-- Dialog wrapper for Timesheet create/edit --}}
@php
    $isEdit = (bool) $timesheet->exists;
    $action = $isEdit
        ? route('projects.timesheets.update', [$project, $timesheet])
        : route('projects.timesheets.store', $project);
@endphp
<x-modal
    :title="$isEdit ? __('Stundenzettel bearbeiten') : __('Stundenzettel anlegen')"
    :eyebrow="$project->name"
    icon="description"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    @include('timesheets._form_body')

    @if ($isEdit)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('projects.timesheets.destroy', [$project, $timesheet]) }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Stundenzettel wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Löschen') }}</button>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
