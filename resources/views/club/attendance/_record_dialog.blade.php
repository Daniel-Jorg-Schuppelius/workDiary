{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _record_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Nachweis korrigieren (in #entry-modal geladen). Nach der ersten Bestätigung ist ein Grund Pflicht.
  Variablen: $event, $sheet, $record (mit member, revisions), $maxMinutes, $statuses
--}}
<x-modal
    :title="__('club.attendance.action.correct')"
    :eyebrow="$record->member?->fullName()"
    icon="edit"
    tone="primary"
    :action="route('club.events.attendance.records.update', [$event, $record])"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <input type="hidden" name="version" value="{{ $sheet->version }}">
    <x-form-group :legend="__('club.field.status')" icon="fact_check" tone="primary" cols="2" :description="__('club.attendance.hint.minutes', ['max' => $maxMinutes])">
        <x-select-field name="status" :label="__('club.field.status')" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $record->status->value) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="minutes" type="number" min="0" :max="$maxMinutes" :label="__('club.attendance.field.minutes')" :value="old('minutes', $record->minutes)" />
        <x-input-field name="arrived_at" type="time" :label="__('club.attendance.field.arrived_at')" :value="old('arrived_at', $record->arrived_at?->orgTz()->format('H:i'))" />
        <x-input-field name="left_at" type="time" :label="__('club.attendance.field.left_at')" :value="old('left_at', $record->left_at?->orgTz()->format('H:i'))" />
        <x-input-field name="reason" :label="__('club.attendance.field.reason')" :required="$sheet->requiresReason()" maxlength="255" span="2" :value="old('reason')" :hint="$sheet->requiresReason() ? __('club.attendance.hint.reason') : null" />
    </x-form-group>

    @if ($record->revisions->isNotEmpty())
        <div class="mt-2">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('club.attendance.label.revisions') }}</p>
            <ul class="mt-1 space-y-1 text-xs">
                @foreach ($record->revisions as $revision)
                    <li>
                        {{ $revision->created_at?->orgTz()->format('d.m.Y H:i') }} · {{ $revision->actor?->name ?? '–' }}:
                        {{ $revision->previous_status?->label() ?? '–' }} {{ $revision->previous_minutes ?? '' }}
                        <x-icon name="arrow_forward" class="text-muted" />
                        {{ $revision->status->label() }} {{ $revision->minutes ?? '' }} — {{ $revision->reason }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-modal>
