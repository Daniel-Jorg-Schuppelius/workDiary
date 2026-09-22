{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _register_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Mitglied anmelden oder einladen (in #entry-modal geladen).
  Variablen: $event, $details, $targets (Soll-Liste ohne aktive Teilnahme), $others (übrige aktuelle Mitglieder), $seats
--}}
<x-modal
    :title="__('club.events.action.register')"
    :eyebrow="$event->title"
    icon="person_add"
    tone="primary"
    :action="route('club.events.register', $event)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.field.member')" icon="person_add" tone="primary" cols="2" :description="$seats['max'] !== null ? __('club.events.label.seats', ['taken' => $seats['taken'], 'max' => $seats['max']]) : __('club.events.label.seats_unlimited', ['taken' => $seats['taken']])">
        <x-select-field name="club_member_id" :label="__('club.field.member')" required span="2">
            <option value="">–</option>
            @if ($targets->isNotEmpty())
                <optgroup label="{{ __('club.events.field.target_list') }}">
                    @foreach ($targets as $member)
                        <option value="{{ $member->sqid }}" @selected((string) old('club_member_id') === $member->sqid)>{{ $member->fullName() }} · {{ $member->displayNo() }}</option>
                    @endforeach
                </optgroup>
            @endif
            @if ($others->isNotEmpty())
                <optgroup label="{{ __('club.events.label.other_members') }}">
                    @foreach ($others as $member)
                        <option value="{{ $member->sqid }}" @selected((string) old('club_member_id') === $member->sqid)>{{ $member->fullName() }} · {{ $member->displayNo() }}</option>
                    @endforeach
                </optgroup>
            @endif
        </x-select-field>
        <x-select-field name="mode" :label="__('club.events.field.mode')" required>
            <option value="register" @selected(old('mode', 'register') === 'register')>{{ __('club.events.mode.register') }}</option>
            <option value="invite" @selected(old('mode') === 'invite')>{{ __('club.events.mode.invite') }}</option>
        </x-select-field>
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" :value="old('note')" />
        <x-checkbox-field name="spontaneous" :label="__('club.events.field.spontaneous')" :checked="(bool) old('spontaneous', false)" span="2" :hint="__('club.events.hint.spontaneous')" />
    </x-form-group>
</x-modal>
