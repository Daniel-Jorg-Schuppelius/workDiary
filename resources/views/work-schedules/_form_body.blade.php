{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Shared form fields for WorkSchedule. Läuft im x-data="wsForm(...)"-Scope
     des umschließenden <x-modal> (siehe _form_dialog). Der Minuten/Stunden-
     Umschalter sitzt im Dialog-Header; sichtbare Felder zeigen die gewählte
     Einheit, versteckte Felder posten ganze Minuten bzw. (pro Tag) Stunden. --}}
@php
    use App\Enums\WorkSchedule\ScheduleType;

    $workingDays = old('working_days', (array) ($schedule->working_days ?? [1, 2, 3, 4, 5]));
    $weekdayLabels = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
@endphp

{{-- Arbeitszeit-Typ --}}
<x-form-group :legend="__('Arbeitszeit-Typ')" icon="tune" tone="primary" cols="1">
    <div class="fieldset">
        <select name="schedule_type" x-model="type" class="select select-bordered w-full">
            @foreach (ScheduleType::options() as $val => $lbl)
                <option value="{{ $val }}">{{ $lbl }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-muted">
            <template x-if="isType('flextime')"><span>{{ __('work_schedule.type_hint.flextime') }}</span></template>
            <template x-if="isType('weekly')"><span>{{ __('work_schedule.type_hint.weekly') }}</span></template>
            <template x-if="isType('per_weekday')"><span>{{ __('work_schedule.type_hint.per_weekday') }}</span></template>
            <template x-if="isType('trust')"><span>{{ __('work_schedule.type_hint.trust') }}</span></template>
        </p>
    </div>
</x-form-group>

{{-- Wochensoll / Tagessoll (Gleitzeit + feste Wochenarbeitszeit) --}}
<x-form-group :legend="__('Arbeitszeit')" icon="schedule" tone="primary" cols="2"
              x-show="isTypeAny('flextime', 'weekly')" x-cloak>
    <div class="fieldset">
        <label for="weekly_minutes" class="fieldset-label">{{ __('Wochenarbeitszeit') }} (<span x-text="unitLabel"></span>) *</label>
        <input type="hidden" name="weekly_minutes" :value="toMin(d.weekly)">
        <input id="weekly_minutes" type="number" min="0" :step="step" x-model="d.weekly" class="input input-bordered w-full">
    </div>
    <div class="fieldset" x-show="isType('flextime')">
        <label for="daily_target_minutes" class="fieldset-label">{{ __('Tagessoll') }} (<span x-text="unitLabel"></span>) *</label>
        <input type="hidden" name="daily_target_minutes" :value="toMin(d.daily)">
        <input id="daily_target_minutes" type="number" min="0" :step="step" x-model="d.daily" class="input input-bordered w-full">
    </div>
</x-form-group>

{{-- Arbeitstage (Gleitzeit / Wochenarbeitszeit / Vertrauensarbeitszeit) --}}
<x-form-group :legend="__('Arbeitstage')" icon="event" tone="primary" cols="1"
              x-show="isTypeAny('flextime', 'weekly', 'trust')" x-cloak>
    <div class="fieldset">
        <p class="text-xs text-muted" x-show="isType('trust')">{{ __('Tage, an denen Anwesenheit erwartet wird.') }}</p>
        <div class="mt-1 flex flex-wrap gap-3">
            @foreach ($weekdayLabels as $iso => $lbl)
                <label class="label cursor-pointer gap-1">
                    <input type="checkbox" name="working_days[]" value="{{ $iso }}" class="checkbox checkbox-xs" @checked(in_array($iso, $workingDays))>
                    <span class="fieldset-label">{{ $lbl }}</span>
                </label>
            @endforeach
        </div>
    </div>
</x-form-group>

{{-- Wochentagsweise: pro Tag Stunden ODER Von–bis --}}
<x-form-group :legend="__('Wochentage')" icon="calendar_view_week" tone="primary" cols="1"
              x-show="isType('per_weekday')" x-cloak>
    <div class="overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>{{ __('Tag') }}</th>
                    <th>{{ __('Erfassung') }}</th>
                    <th>{{ __('Vorgabe') }}</th>
                    <th class="text-right">{{ __('Tagessoll') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($weekdayLabels as $iso => $lbl)
                    <tr :class="dayRowClass({{ $iso }})">
                        <td class="whitespace-nowrap">
                            <label class="label cursor-pointer gap-2 p-0">
                                <input type="checkbox" class="checkbox checkbox-xs" x-model="days.d{{ $iso }}.enabled">
                                <span class="font-medium">{{ $lbl }}</span>
                            </label>
                            {{-- Hidden-Felder fürs Posten (Minuten/Stunden serverseitig normalisiert) --}}
                            <input type="hidden" name="day_targets[{{ $iso }}][enabled]" :value="dayEnabledValue({{ $iso }})">
                            <input type="hidden" name="day_targets[{{ $iso }}][hours]" :value="dayHours({{ $iso }})">
                        </td>
                        <td>
                            <select class="select select-bordered select-sm" x-model="days.d{{ $iso }}.mode" name="day_targets[{{ $iso }}][mode]" :disabled="dayDisabled({{ $iso }})">
                                <option value="hours">{{ __('Stunden') }}</option>
                                <option value="times">{{ __('Von–bis') }}</option>
                            </select>
                        </td>
                        <td>
                            <div x-show="dayModeIs({{ $iso }}, 'hours')" class="flex items-center gap-1">
                                <input type="number" min="0" :step="step" x-model="days.d{{ $iso }}.hours" :disabled="dayDisabled({{ $iso }})" class="input input-bordered input-sm w-24">
                                <span class="text-xs text-muted" x-text="unitLabel"></span>
                            </div>
                            <div x-show="dayModeIs({{ $iso }}, 'times')" class="flex flex-wrap items-center gap-1">
                                <input type="time" name="day_targets[{{ $iso }}][start]" x-model="days.d{{ $iso }}.start" :disabled="dayDisabled({{ $iso }})" class="input input-bordered input-sm w-28">
                                <span class="text-xs">–</span>
                                <input type="time" name="day_targets[{{ $iso }}][end]" x-model="days.d{{ $iso }}.end" :disabled="dayDisabled({{ $iso }})" class="input input-bordered input-sm w-28">
                                <input type="number" min="0" name="day_targets[{{ $iso }}][break]" x-model="days.d{{ $iso }}.break" :disabled="dayDisabled({{ $iso }})" class="input input-bordered input-sm w-16" title="{{ __('Pause (Min.)') }}">
                                <span class="text-xs text-muted">{{ __('Pause') }}</span>
                            </div>
                        </td>
                        <td class="text-right text-sm tabular-nums" x-text="dayMinutesLabel({{ $iso }})"></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-right">{{ __('Wochensoll') }}</th>
                    <th class="text-right tabular-nums" x-text="weeklyTotalFmt"></th>
                </tr>
            </tfoot>
        </table>
    </div>
</x-form-group>

{{-- Kern- & Rahmenzeit (nur Gleitzeit) --}}
<x-form-group :legend="__('Kernzeit & Rahmenzeit')" icon="schedule" tone="info" cols="2"
              x-show="isType('flextime')" x-cloak>
    <x-input-field name="core_start" type="time" :label="__('Kernzeit Start')" :value="old('core_start', substr((string) $schedule->core_start, 0, 5))" />
    <x-input-field name="core_end" type="time" :label="__('Kernzeit Ende')" :value="old('core_end', substr((string) $schedule->core_end, 0, 5))" />
    <x-input-field name="frame_start" type="time" :label="__('Rahmenzeit Start')" :value="old('frame_start', substr((string) $schedule->frame_start, 0, 5))" />
    <x-input-field name="frame_end" type="time" :label="__('Rahmenzeit Ende')" :value="old('frame_end', substr((string) $schedule->frame_end, 0, 5))" />
</x-form-group>

{{-- Pausen & Gültigkeit (für alle Typen; Pflichtpause ist gesetzlich) --}}
<x-form-group :legend="__('Pausen & Gültigkeit')" icon="restaurant" tone="success" cols="2">
    <div class="fieldset">
        <label for="break_after_minutes" class="fieldset-label">{{ __('Pause ab') }} (<span x-text="unitLabel"></span>) *</label>
        <input type="hidden" name="break_after_minutes" :value="toMin(d.breakAfter)">
        <input id="break_after_minutes" type="number" min="0" :step="step" x-model="d.breakAfter" class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label for="break_minutes" class="fieldset-label">{{ __('Pflichtpause') }} (<span x-text="unitLabel"></span>) *</label>
        <input type="hidden" name="break_minutes" :value="toMin(d.breakMin)">
        <input id="break_minutes" type="number" min="0" :step="step" x-model="d.breakMin" class="input input-bordered w-full">
    </div>
    <x-date-range class="md:col-span-2" layout="split" form-control
                  from-name="valid_from" to-name="valid_to" from-required
                  :from="old('valid_from', optional($schedule->valid_from)->format('Y-m-d'))"
                  :to="old('valid_to', optional($schedule->valid_to)->format('Y-m-d'))"
                  :from-label="__('Gültig ab')" :to-label="__('Gültig bis')" />
</x-form-group>

<x-validation-errors />
