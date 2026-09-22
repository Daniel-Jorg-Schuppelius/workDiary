{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _decide_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Vorschlag bestätigen (in #entry-modal geladen): Wirksamkeitsdatum, optionale
  Zielgruppe, Ausnahme nur mit Recht und Begründung.
  Variablen: $proposal (mit member, group, suggestedGroup), $targets, $today, $canOverride
--}}
@php($selectedTarget = old('suggested_group_id', $proposal->suggestedGroup?->sqid ?? ''))

<x-modal
    :title="__('club.action.confirm')"
    :eyebrow="$proposal->member?->fullName() . ' · ' . $proposal->group?->name"
    icon="swap_horiz"
    tone="primary"
    :action="route('club.proposals.confirm', $proposal)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.confirm')">

    <x-form-group :legend="__('club.field.reason')" icon="rule" tone="primary" cols="1" :description="__('club.hint.proposals')">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <x-status-badge :tone="$proposal->reason->tone()" size="sm">{{ $proposal->reason->label() }}</x-status-badge>
            @if ($proposal->group?->ageRangeLabel())
                <span class="text-muted">{{ $proposal->group->ageRangeLabel() }}</span>
            @endif
            @if ($proposal->member?->birth_date)
                <span class="text-muted">· {{ trans_choice('club.label.age_years', $proposal->member->ageOn($today) ?? 0, ['age' => $proposal->member->ageOn($today) ?? 0]) }}</span>
            @endif
        </div>
    </x-form-group>

    <x-form-group :legend="__('club.field.effective_on')" icon="event" tone="primary" cols="2">
        <x-input-field name="effective_on" type="date" :label="__('club.field.effective_on')" required :value="old('effective_on', $today->toDateString())" />
        <x-select-field name="suggested_group_id" :label="__('club.field.suggested_group')" :hint="$proposal->suggestedGroup ? null : __('club.hint.no_suggestion')">
            <option value="">{{ __('club.label.no_target') }}</option>
            @foreach ($targets as $target)
                <option value="{{ $target->sqid }}" @selected((string) $selectedTarget === $target->sqid)>
                    {{ $target->name }}@if ($target->ageRangeLabel()) · {{ $target->ageRangeLabel() }}@endif
                </option>
            @endforeach
        </x-select-field>
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" span="2" :value="old('note')" />
        @if ($canOverride)
            <x-checkbox-field name="override" :label="__('club.field.override')" :checked="(bool) old('override', false)" span="2" :hint="__('club.hint.override')" />
        @endif
    </x-form-group>
</x-modal>
