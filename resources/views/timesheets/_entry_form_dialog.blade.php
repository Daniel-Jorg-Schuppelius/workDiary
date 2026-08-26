{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entry_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog for adding a TimeEntry row to a Timesheet.
     Erwartet: $project, $timesheet, $tasks, $recentDescriptions,
     $allTags, $selectedTagIds, $recentTagIds --}}
@php
    // Von/Bis bleibt der Standard — der unterschriebene Zettel weist die
    // Einsatzzeiten aus. Dauer ist der schnelle Weg fürs Nacherfassen.
    $initialMode = old('_time_mode', 'range');
    $currentMinutes = (int) old('minutes', 0);
    $hh = str_pad((string) intdiv($currentMinutes, 60), 2, '0', STR_PAD_LEFT);
    $mm = str_pad((string) ($currentMinutes % 60), 2, '0', STR_PAD_LEFT);
    $descriptionListId = 'ts-entry-descriptions-' . $timesheet->id;
@endphp
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
    <input type="hidden" name="date" value="{{ optional($timesheet->work_date)->toDateString() }}">
    {{-- Wird im Dauer-Modus per JS aus HH:MM befüllt; im Von/Bis-Modus rechnet
         der Model-Hook die Minuten aus started_at/ended_at. --}}
    <input type="hidden" name="minutes" data-time-minutes value="{{ $currentMinutes }}">

    <x-form-group :legend="__('Zeit')" icon="schedule" tone="primary" cols="2">
        <div class="fieldset md:col-span-2" data-time-mode-toggle>
            <span class="fieldset-label">{{ __('Erfassungsart') }}</span>
            <div class="join">
                <input type="radio" name="_time_mode" value="range"
                       data-time-mode-radio data-target="range"
                       class="join-item btn btn-sm"
                       aria-label="{{ __('Von / Bis') }}"
                       @checked($initialMode === 'range')>
                <input type="radio" name="_time_mode" value="duration"
                       data-time-mode-radio data-target="duration"
                       class="join-item btn btn-sm"
                       aria-label="{{ __('Dauer') }}"
                       @checked($initialMode === 'duration')>
            </div>
        </div>

        {{-- Pane-Wrapper: das JS (initDynamicFields) versteckt/disabled das
             Element mit data-time-mode-pane samt Kind-Inputs — der Wrapper
             bleibt daher außerhalb der Feld-Komponente. --}}
        <div data-time-mode-pane="range">
            <x-input-field name="start_time" type="time" :label="__('Start (Uhrzeit)')" />
        </div>
        <div data-time-mode-pane="range">
            <x-input-field name="end_time" type="time" :label="__('Ende (Uhrzeit)')" />
        </div>
        <p class="text-xs text-muted md:col-span-2" data-time-mode-pane="range">
            {{ __('Datum stammt aus dem Stundenzettel (:date). Endet die Zeit nach Mitternacht? Einfach die kleinere Uhrzeit eintragen.', ['date' => optional($timesheet->work_date)->fdate()]) }}
        </p>
        <div data-time-mode-pane="range">
            <x-input-field name="break_minutes" type="number" :label="__('Pause (Min.)')" value="0" min="0" max="480" />
        </div>

        <div class="fieldset" data-time-mode-pane="duration">
            {{-- Bewusst ohne name: das JS spiegelt HH:MM in das Hidden-Feld
                 minutes — deshalb Roh-Input mit manueller Label-/Fehler-Verknüpfung. --}}
            <label class="fieldset-label" for="ts-entry-hhmm">{{ __('Dauer (HH:MM)') }}</label>
            <input type="text" data-time-hhmm id="ts-entry-hhmm"
                   class="input input-bordered w-full"
                   pattern="^\d{1,2}:[0-5]\d$"
                   placeholder="1:30"
                   @error('minutes') aria-invalid="true" aria-describedby="ts-entry-hhmm-error" @enderror
                   value="{{ $hh }}:{{ $mm }}">
            @error('minutes')<p id="ts-entry-hhmm-error" class="text-error text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <x-select-field name="kind" :label="__('Art')">
            <option value="work">{{ __('Arbeit') }}</option>
            <option value="travel">{{ __('Anfahrt') }}</option>
            <option value="standby">{{ __('Bereitschaft') }}</option>
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Bezug')" icon="task" tone="info" cols="1">
        <x-select-field name="task_id" :label="__('Aufgabe')">
            <option value="">—</option>
            @foreach ($tasks as $t)
                <option value="{{ $t->sqid }}" @selected((string) old('task_id') === $t->sqid)>{{ $t->title }}</option>
            @endforeach
        </x-select-field>
        {{-- Vorschlagsliste aus den letzten Buchungstexten dieses Projekts —
             dasselbe Prinzip wie der Quick-Pick der Heute-Leiste, hier ohne
             eigenes JS über <datalist>. --}}
        <x-input-field name="description" :label="__('Beschreibung')" maxlength="500"
                       list="{{ $descriptionListId }}"
                       autocomplete="off"
                       :placeholder="__('Woran hast du gearbeitet?')"
                       :value="old('description')" />
        @if (! empty($recentDescriptions))
            <datalist id="{{ $descriptionListId }}">
                @foreach ($recentDescriptions as $suggestion)
                    <option value="{{ $suggestion }}"></option>
                @endforeach
            </datalist>
        @endif
        <div class="fieldset">
            <span class="fieldset-label">{{ __('Tags') }}</span>
            <x-tag-picker :tags="$allTags" :selected="$selectedTagIds" :recent="$recentTagIds" />
        </div>
    </x-form-group>

    <x-validation-errors />
</x-modal>
