{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entry_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog for adding a TimeEntry row to a Timesheet --}}
<x-modal
    :title="__('Zeile hinzufügen')"
    :eyebrow="__('Zeiteintrag')"
    icon="schedule"
    tone="primary"
    :action="route('projects.timesheets.entries.store', [$project, $timesheet])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Hinzufügen')"
>
    <x-form-group :legend="__('Zeit')" icon="schedule" tone="primary" cols="2">
        <input type="hidden" name="date" value="{{ optional($timesheet->work_date)->toDateString() }}">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Start (Uhrzeit)') }} *</label>
            <input type="time" name="start_time" required class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Ende (Uhrzeit)') }} *</label>
            <input type="time" name="end_time" required class="input input-bordered w-full">
        </div>
        <p class="text-xs text-base-content/60 md:col-span-2">
            {{ __('Datum stammt aus dem Stundenzettel (:date). Endet die Zeit nach Mitternacht? Einfach die kleinere Uhrzeit eintragen.', ['date' => optional($timesheet->work_date)->fdate()]) }}
        </p>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Pause (Min.)') }}</label>
            <input type="number" name="break_minutes" value="0" min="0" max="480" class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Art') }}</label>
            <select name="kind" class="select select-bordered w-full">
                <option value="work">{{ __('Arbeit') }}</option>
                <option value="travel">{{ __('Anfahrt') }}</option>
                <option value="standby">{{ __('Bereitschaft') }}</option>
            </select>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Bezug')" icon="task" tone="info" cols="1">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Aufgabe') }}</label>
            <select name="task_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($tasks as $t)
                    <option value="{{ $t->sqid }}" @selected((string) old('task_id') === $t->sqid)>{{ $t->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Beschreibung') }}</label>
            <input type="text" name="description" maxlength="500" class="input input-bordered w-full">
        </div>
    </x-form-group>

    <x-validation-errors />
</x-modal>
