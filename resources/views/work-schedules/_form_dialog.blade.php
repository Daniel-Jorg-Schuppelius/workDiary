{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog wrapper for WorkSchedule edit.
     Das Alpine-x-data hängt an <x-modal> (merged auf die .wd-dialog-Wrapper-Div),
     damit Header (Einheiten-Umschalter) und Body (Felder) denselben Scope teilen. --}}
@php
    use App\Enums\WorkSchedule\ScheduleType;

    $days = old('working_days', (array) ($schedule->working_days ?? [1, 2, 3, 4, 5]));
    $storedTargets = old('day_targets', (array) ($schedule->day_targets ?? []));

    // Pro-Wochentag-Initialwerte (Minuten als Start-Einheit). Aktive Tage und
    // Stunden werden – falls keine day_targets vorliegen – aus Arbeitstagen +
    // Tagessoll vorbelegt, damit der Wechsel auf „Wochentagsweise" sinnvoll startet.
    $wsDays = [];
    foreach ([1, 2, 3, 4, 5, 6, 7] as $iso) {
        $t = $storedTargets[$iso] ?? $storedTargets[(string) $iso] ?? null;
        $wsDays[$iso] = [
            'enabled' => $t !== null || in_array($iso, $days),
            'mode' => $t['mode'] ?? 'hours',
            'hours' => isset($t['minutes']) ? (int) $t['minutes'] : (int) ($schedule->daily_target_minutes ?? 0),
            'start' => $t['start'] ?? '08:00',
            'end' => $t['end'] ?? '16:00',
            'break' => isset($t['break']) ? (int) $t['break'] : 30,
        ];
    }

    $wsInit = [
        'type' => old('schedule_type', $schedule->schedule_type instanceof ScheduleType
            ? $schedule->schedule_type->value
            : ($schedule->schedule_type ?? 'flextime')),
        'unit' => 'minutes',
        'weekly' => (int) old('weekly_minutes', $schedule->weekly_minutes ?? 0),
        'daily' => (int) old('daily_target_minutes', $schedule->daily_target_minutes ?? 0),
        'breakAfter' => (int) old('break_after_minutes', $schedule->break_after_minutes ?? 0),
        'breakMin' => (int) old('break_minutes', $schedule->break_minutes ?? 0),
        'days' => $wsDays,
    ];
@endphp

<x-modal
    :title="__('Arbeitszeit-Modell')"
    :eyebrow="$user->name"
    icon="schedule"
    tone="primary"
    :action="route('users.work-schedule.update', $user)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
    x-data="wsForm"
    :data-config="json_encode($wsInit)"
>
    <x-slot:headerActions>
        <div class="join rounded-box border border-base-300/70">
            <button type="button" class="join-item btn btn-sm"
                    :class="unitClass('minutes')"
                    @click="switchTo('minutes')">{{ __('Minuten') }}</button>
            <button type="button" class="join-item btn btn-sm"
                    :class="unitClass('hours')"
                    @click="switchTo('hours')">{{ __('Stunden') }}</button>
        </div>
    </x-slot:headerActions>

    @include('work-schedules._form_body')
</x-modal>
