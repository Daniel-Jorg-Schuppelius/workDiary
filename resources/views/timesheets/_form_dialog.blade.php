{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
            <x-action-form :action="route('projects.timesheets.destroy', [$project, $timesheet])" method="DELETE"
                  :confirm="__('Stundenzettel wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
