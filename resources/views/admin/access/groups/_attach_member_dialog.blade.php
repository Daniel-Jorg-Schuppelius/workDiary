{{--
  Created on   : Thu May 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _attach_member_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Mitglied einer Gruppe hinzufügen. --}}
@php
    /** @var \App\Models\UserGroup $group */
    /** @var \Illuminate\Database\Eloquent\Collection $addableUsers */
@endphp
<x-modal
    :title="__('access.field.add_member')"
    :eyebrow="$group->name"
    icon="person_add"
    tone="primary"
    size="md"
    :action="route('admin.access.groups.members.attach', $group)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('access.action.add')"
>
    @if ($addableUsers->isEmpty())
        <p class="text-sm text-muted">{{ __('access.empty.members') }}</p>
    @else
        <x-form-group :label="__('access.field.add_member')" name="user_id" required>
            <x-user-select name="user_id" :users="$addableUsers" required />
        </x-form-group>
    @endif
</x-modal>
