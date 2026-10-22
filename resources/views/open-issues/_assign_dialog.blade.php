{{--
  Created on   : Wed Oct 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-modal
    :title="__('open-issue.action.assign')"
    :eyebrow="$issue->title"
    icon="person_add"
    tone="primary"
    size="sm"
    :action="route('open-issues.assign', $issue)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <x-select-field name="assignee_user_id" :label="__('open-issue.field.assignee')">
        <option value="">{{ __('Unzugewiesen') }}</option>
        @foreach ($users as $user)
            <option value="{{ $user->sqid }}" @selected((string) old('assignee_user_id', $issue->assignee?->sqid) === $user->sqid)>{{ $user->name }}</option>
        @endforeach
    </x-select-field>
</x-modal>
