{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _role_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Terminrolle vergeben (in #entry-modal geladen). Variablen: $event, $roles, $members, $users. --}}
<x-modal
    :title="__('club.matches.action.add_role')"
    :eyebrow="$event->title"
    icon="sports"
    tone="primary"
    :action="route('club.matches.roles.store', $event)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <x-form-group :legend="__('club.matches.card.roles')" icon="sports" tone="primary" cols="2" :description="__('club.matches.hint.roles')">
        <x-select-field name="role" :label="__('club.matches.field.role')" required span="2">
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', 'referee') === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="club_member_id" :label="__('club.field.member')">
            <option value="">–</option>
            @foreach ($members as $member)
                <option value="{{ $member->sqid }}" @selected(old('club_member_id') === $member->sqid)>{{ $member->last_name }}, {{ $member->first_name }} ({{ $member->member_no }})</option>
            @endforeach
        </x-select-field>
        <x-select-field name="user_id" :label="__('club.matches.field.staff_user')">
            <option value="">–</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected(old('user_id') === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="name" :label="__('club.matches.field.external_name')" maxlength="120" :value="old('name')" :hint="__('club.matches.hint.external_name')" />
        <x-input-field name="note" :label="__('club.field.notes')" maxlength="255" :value="old('note')" />
    </x-form-group>
</x-modal>
