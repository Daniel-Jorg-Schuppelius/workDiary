{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _attendance_correct_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eigene Stempelung korrigieren (MVP-1595): Standalone-Dialog je Stempelung,
     damit er auch ohne Verbindung öffnet; offline geht er als Befehl
     `attendance.correct` mit dem gezeigten Stand (base_version) in die
     Warteschlange. Erwartet $attendance. --}}
@php
    $local = static fn (?\Carbon\CarbonInterface $at): ?string => $at !== null ? \App\Support\Tz::toLocal($at)?->format('Y-m-d\TH:i') : null;
    $version = $attendance->correctionVersion();
@endphp
<x-modal :id="'attendance-correct-' . $attendance->sqid"
         :embedded="false"
         icon="edit_note"
         tone="warning"
         :eyebrow="__('Stempelungen')"
         :title="__('time-correction.attendance.title')"
         :action="route('attendance.correct', $attendance)"
         method="POST"
         :form-data="['data-offline-sync' => 'attendance.correct', 'data-sync-payload-attendance' => $attendance->sqid, 'data-sync-payload-base-version' => $version]"
         :submitLabel="__('time-correction.attendance.submit')"
         submitClass="btn-warning">
    <input type="hidden" name="base_version" value="{{ $version }}" />

    <p class="text-sm text-muted">{{ __('time-correction.attendance.intro') }}</p>

    <x-date-range type="datetime-local" layout="join" fromName="started_at" toName="ended_at"
                  :from="$local($attendance->started_at)" :to="$local($attendance->ended_at)"
                  :label="__('time-correction.attendance.span')" :fromLabel="__('Von')" :toLabel="__('Bis')"
                  :fromId="'attendance-correct-from-' . $attendance->sqid" :toId="'attendance-correct-to-' . $attendance->sqid"
                  size="" />

    <x-input-field name="break_minutes" :id="'attendance-correct-break-' . $attendance->sqid" :label="__('Pause (Minuten)')" type="number" min="0" max="600"
                   :value="(int) ($attendance->break_minutes_manual ?? 0)" />

    <div class="fieldset">
        <label class="fieldset-label" for="attendance-correct-reason-{{ $attendance->sqid }}">{{ __('time-correction.attendance.reason') }}</label>
        <textarea id="attendance-correct-reason-{{ $attendance->sqid }}" name="reason" required rows="3"
                  minlength="{{ \App\Services\TimeApproval\TimeCorrectionService::REASON_MIN_LENGTH }}" maxlength="4000"
                  class="textarea textarea-bordered w-full"
                  placeholder="{{ __('time-correction.attendance.reason_placeholder', ['min' => \App\Services\TimeApproval\TimeCorrectionService::REASON_MIN_LENGTH]) }}"></textarea>
    </div>
</x-modal>
