{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _performance_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Leistung erfassen/korrigieren (in #entry-modal geladen). Variablen: $member, $performance|null, $profiles (mit disciplines). --}}
@php($isEdit = $performance !== null)
<x-modal
    :title="$isEdit ? __('club.competitions.action.correct_performance') : __('club.competitions.action.record_performance')"
    :eyebrow="$member->fullName()"
    icon="timer"
    tone="primary"
    :action="$isEdit ? route('club.members.performances.update', [$member, $performance]) : route('club.members.performances.store', $member)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <x-form-group :legend="__('club.competitions.card.performances')" icon="timer" tone="primary" cols="2" :description="$isEdit ? __('club.competitions.hint.correct') : __('club.competitions.hint.performance')">
        @if ($isEdit)
            <p class="text-sm md:col-span-2">{{ $performance->profile?->name }} · {{ $performance->discipline_code }} · {{ $performance->performed_on->format('d.m.Y') }}</p>
        @else
            <x-select-field name="club_sport_profile_id" :label="__('club.teams.field.profile')" required>
                <option value="">–</option>
                @foreach ($profiles as $profile)
                    <option value="{{ $profile->sqid }}" @selected(old('club_sport_profile_id') === $profile->sqid)>{{ $profile->name }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="discipline_code" :label="__('club.competitions.field.discipline')" required>
                <option value="">–</option>
                @foreach ($profiles as $profile)
                    <optgroup label="{{ $profile->name }}">
                        @foreach ($profile->disciplines ?? [] as $discipline)
                            <option value="{{ $discipline['code'] }}" @selected(old('discipline_code') === $discipline['code'])>{{ $discipline['label'] }}@if ($discipline['unit'] ?? null) ({{ $discipline['unit'] }})@endif</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-select-field>
            <x-input-field name="performed_on" type="date" :label="__('club.competitions.field.performed_on')" :value="old('performed_on', now()->toDateString())" />
        @endif
        <x-input-field name="value" :label="__('club.competitions.field.value')" required maxlength="20" :value="old('value', $performance?->value)" :hint="__('club.competitions.hint.value')" />
        <x-input-field name="placement" type="number" min="1" max="9999" :label="__('club.competitions.field.placement')" :value="old('placement', $performance?->placement)" />
        <x-input-field name="note" :label="__('club.field.notes')" maxlength="255" span="2" :value="old('note', $performance?->note)" />
        @unless ($isEdit)
            <x-checkbox-field name="confirm" :label="__('club.competitions.field.confirm')" :checked="(bool) old('confirm', false)" span="2" />
        @endunless
    </x-form-group>
</x-modal>
