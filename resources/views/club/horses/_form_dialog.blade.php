{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Pferd anlegen/bearbeiten (in #entry-modal geladen). Variablen: $horse|null (mit groups, resource), $members, $groups. --}}
@php
    $isEdit = $horse !== null;
    $selectedOwner = old('owner_member_id', $horse?->owner_member_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubMember::class, $horse->owner_member_id) : '');
    $selectedGroups = collect(old('group_ids', $horse?->groups->pluck('sqid')->all() ?? []))->map(fn ($v) => (string) $v)->all();
@endphp
<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.horses.action.create')"
    :eyebrow="__('club.horses.title.index')"
    icon="bedroom_baby"
    tone="primary"
    size="wide"
    :action="$isEdit ? route('club.horses.update', $horse) : route('club.horses.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <x-form-group :legend="__('club.card.master_data')" icon="bedroom_baby" tone="primary" cols="2" :description="__('club.horses.hint.form')">
        <x-input-field name="name" :label="__('club.field.name')" required maxlength="120" :value="old('name', $horse?->name)" />
        <x-select-field name="kind" :label="__('club.horses.field.kind')" required>
            @foreach (\App\Enums\Club\ClubHorseKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $horse?->kind->value ?? 'school') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="owner_member_id" :label="__('club.horses.field.owner')" :hint="__('club.horses.hint.owner')">
            <option value="">–</option>
            @foreach ($members as $member)
                <option value="{{ $member->sqid }}" @selected((string) $selectedOwner === $member->sqid)>{{ $member->last_name }}, {{ $member->first_name }} ({{ $member->member_no }})</option>
            @endforeach
        </x-select-field>
        <x-input-field name="contact" :label="__('club.horses.field.contact')" maxlength="190" :value="old('contact', $horse?->contact)" />
        <x-input-field name="suitable_for" :label="__('club.horses.field.suitable_for')" maxlength="255" span="2" :value="old('suitable_for', $horse?->suitable_for)" :hint="__('club.horses.hint.suitable_for')" />
        <x-input-field name="max_uses_per_day" type="number" min="1" max="20" :label="__('club.horses.field.max_uses_per_day')" :value="old('max_uses_per_day', $horse?->max_uses_per_day)" :hint="__('club.horses.hint.max_uses')" />
        <x-input-field name="rest_minutes" type="number" min="0" max="600" :label="__('club.horses.field.rest_minutes')" :value="old('rest_minutes', $horse?->resource?->teardown_minutes ?? 30)" :hint="__('club.horses.hint.rest_minutes')" />
        <x-checkbox-field name="requires_clearance" :label="__('club.horses.field.requires_clearance')" :checked="(bool) old('requires_clearance', $horse?->resource?->requires_clearance ?? true)" />
        <x-checkbox-field name="is_active" :label="__('club.field.is_active')" :checked="(bool) old('is_active', $horse?->is_active ?? true)" />
        <x-textarea-field name="notes" :label="__('club.field.notes')" rows="2" maxlength="2000" span="2" :value="old('notes', $horse?->notes)" />
    </x-form-group>
    <x-form-group :legend="__('club.horses.field.groups')" icon="diversity_3" tone="ghost" cols="3" :description="__('club.horses.hint.groups')">
        @foreach ($groups as $group)
            <x-checkbox-field name="group_ids[]" :id="'horse-group-' . $group->sqid" :value="$group->sqid" :label="$group->name" :checked="in_array($group->sqid, $selectedGroups, true)" :toggle="false" />
        @endforeach
    </x-form-group>
</x-modal>
