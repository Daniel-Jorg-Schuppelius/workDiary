{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _space_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bereich anlegen bzw. bearbeiten (Feature 191, MVP-1664) mit Leserecht je
  Bereich (MVP-1667). Variablen: $space|null, $roles, $teams, $members.
--}}
@php
    $isEdit = $space !== null;
    $readers = $isEdit ? $space->readers : collect();
    $readerRoles = $readers->where('kind', \App\Enums\Knowledge\KnowledgeAudienceKind::Role)->pluck('role_name')->all();
    $readerTeams = $readers->where('kind', \App\Enums\Knowledge\KnowledgeAudienceKind::Team)->pluck('team_id')->map(static fn ($id): int => (int) $id)->all();
    $readerUsers = $readers->where('kind', \App\Enums\Knowledge\KnowledgeAudienceKind::User)->pluck('user_id')->map(static fn ($id): int => (int) $id)->all();
@endphp
<x-modal
    :title="$isEdit ? __('knowledge_pages.action.edit_space') : __('knowledge_pages.action.create_space')"
    :eyebrow="__('knowledge_pages.title')"
    icon="menu_book"
    tone="primary"
    :action="$isEdit ? route('knowledge.spaces.update', $space) : route('knowledge.spaces.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('knowledge_pages.action.save') : __('knowledge_pages.action.create_space')">

    <x-form-group :legend="__('knowledge_pages.space.title')" icon="menu_book" tone="primary" cols="1">
        <x-input-field name="title" :label="__('knowledge_pages.field.title')" required minlength="2" maxlength="180" :value="old('title', $space?->title)" />
        <x-textarea-field name="description" :label="__('knowledge_pages.field.description')" rows="3" maxlength="2000" :value="old('description', $space?->description)" />
    </x-form-group>

    <x-form-group :legend="__('knowledge_pages.space.readers')" icon="lock" tone="ghost" cols="1">
        <x-checkbox-field name="is_restricted" :label="__('knowledge_pages.space.restrict')" :hint="__('knowledge_pages.space.restrict_hint')" :checked="(bool) old('is_restricted', $space?->is_restricted)" />
        <x-select-field name="roles[]" id="kp-space-roles" :label="__('knowledge_pages.space.reader_roles')" multiple size="4">
            @foreach ($roles as $roleName)
                <option value="{{ $roleName }}" @selected(in_array($roleName, $readerRoles, true))>{{ \App\Enums\User\UserRole::tryFrom($roleName)?->label() ?? $roleName }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="teams[]" id="kp-space-teams" :label="__('knowledge_pages.space.reader_teams')" multiple size="4">
            @foreach ($teams as $team)
                <option value="{{ \App\Support\Sqid::encode(\App\Models\Platform\Team::class, (int) $team->id) }}" @selected(in_array((int) $team->id, $readerTeams, true))>{{ $team->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="users[]" id="kp-space-users" :label="__('knowledge_pages.space.reader_users')" multiple size="6">
            @foreach ($members as $member)
                <option value="{{ \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $member->id) }}" @selected(in_array((int) $member->id, $readerUsers, true))>{{ $member->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>
