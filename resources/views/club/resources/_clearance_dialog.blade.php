{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _clearance_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einweisungs-/Eignungsfreigabe erteilen (in #entry-modal geladen). Variablen: $resource, $members. --}}
<x-modal
    :title="__('club.resources.action.grant_clearance')"
    :eyebrow="$resource->name"
    icon="verified_user"
    tone="primary"
    :action="route('club.resources.clearances.store', $resource)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <x-form-group :legend="__('club.resources.card.clearances')" icon="verified_user" tone="primary" cols="2" :description="__('club.resources.hint.clearance')">
        <x-select-field name="club_member_id" :label="__('club.field.member')" required span="2">
            <option value="">–</option>
            @foreach ($members as $member)
                <option value="{{ $member->sqid }}" @selected(old('club_member_id') === $member->sqid)>{{ $member->last_name }}, {{ $member->first_name }} ({{ $member->member_no }})</option>
            @endforeach
        </x-select-field>
        <x-input-field name="valid_to" type="date" :label="__('club.field.valid_to')" :value="old('valid_to')" :hint="__('club.resources.hint.clearance_valid_to')" />
        <x-input-field name="note" :label="__('club.field.notes')" maxlength="255" :value="old('note')" />
    </x-form-group>
</x-modal>
