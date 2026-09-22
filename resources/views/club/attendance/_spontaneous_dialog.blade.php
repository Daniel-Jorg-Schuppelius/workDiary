{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _spontaneous_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Spontane Teilnahme ergänzen (in #entry-modal geladen). Variablen: $event, $sheet, $candidates --}}
<x-modal
    :title="__('club.attendance.action.spontaneous')"
    :eyebrow="$event->title"
    icon="person_add"
    tone="primary"
    :action="route('club.events.attendance.spontaneous', $event)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <input type="hidden" name="version" value="{{ $sheet->version }}">
    <x-form-group :legend="__('club.field.member')" icon="person_add" tone="primary" cols="1" :description="__('club.attendance.hint.spontaneous')">
        <x-select-field name="club_member_id" :label="__('club.field.member')" required>
            <option value="">–</option>
            @foreach ($candidates as $member)
                <option value="{{ $member->sqid }}" @selected((string) old('club_member_id') === $member->sqid)>{{ $member->fullName() }} · {{ $member->displayNo() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="reason" :label="__('club.attendance.field.reason')" :required="$sheet->requiresReason()" maxlength="255" :value="old('reason')" />
    </x-form-group>
</x-modal>
