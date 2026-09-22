{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _overlap_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Überschneidung klären (in #entry-modal geladen). Variablen: $event, $record (mit member, overlapEvent) --}}
<x-modal
    :title="__('club.attendance.action.clear_overlap')"
    :eyebrow="$record->member?->fullName()"
    icon="rule"
    tone="warning"
    :action="route('club.events.attendance.records.overlap', [$event, $record])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.attendance.action.clear_overlap')">

    <x-form-group :legend="__('club.attendance.label.overlap')" icon="rule" tone="warning" cols="1" :description="__('club.attendance.hint.overlap')">
        @if ($record->overlapEvent)
            <p class="text-sm">
                <a href="{{ route('club.events.attendance.show', $record->overlapEvent) }}" class="link link-primary">{{ $record->overlapEvent->title }}</a>
                <span class="text-muted">· {{ $record->overlapEvent->started_at->orgTz()->format('d.m.Y H:i') }}</span>
            </p>
        @endif
        <x-input-field name="reason" :label="__('club.attendance.field.reason')" required maxlength="255" :value="old('reason')" />
    </x-form-group>
</x-modal>
