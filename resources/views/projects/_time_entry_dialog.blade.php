{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _time_entry_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $project, $entry (null = neu), $tasks, $diaryOptions, $reworkOptions, $goodwillOptions, $isDialog --}}
@php
    $isDialog  = $isDialog ?? false;
    $diaryOptions = $diaryOptions ?? collect();
    $action    = $entry
        ? route('projects.time-entries.update', [$project, $entry])
        : route('projects.time-entries.store', $project);
    $dialogUrl = ($entry
        ? route('projects.time-entries.edit', [$project, $entry])
        : route('projects.time-entries.create', $project)) . '?dialog=1';

    // HH:MM-Wert aus minutes berechnen
    $currentMinutes = old('minutes', $entry?->minutes ?? 60);
    $hh = str_pad((string) intdiv((int) $currentMinutes, 60), 2, '0', STR_PAD_LEFT);
    $mm = str_pad((string) ((int) $currentMinutes % 60), 2, '0', STR_PAD_LEFT);

    // Startmodus: hat der Eintrag schon Von/Bis → Range-Modus, sonst Dauer.
    $hasRange = $entry?->started_at && $entry?->ended_at;
    $initialMode = old('_time_mode', $hasRange ? 'range' : 'duration');
@endphp

<x-modal
    :title="$entry ? __('Zeiteintrag bearbeiten') : __('Zeiteintrag erfassen')"
    :eyebrow="__('Zeiterfassung')"
    icon="timer"
    tone="primary"
    :action="$action"
    :method="$entry ? 'PUT' : 'POST'"
    form-id="time-entry-form"
    :form-data="['data-entry-form' => '']"
    :submit-label="$entry ? __('Speichern') : __('Erfassen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    {{-- Minuten als verstecktes Feld; wird im Dauer-Modus per JS aus HH:MM
         befüllt, im Range-Modus rechnet der Model-Hook aus started_at/ended_at. --}}
    <input type="hidden" name="minutes" data-time-minutes value="{{ $currentMinutes }}">

    <x-form-group :legend="__('Zeit')" icon="timer" tone="primary" cols="2">
            <div class="fieldset md:col-span-2" data-time-mode-toggle>
                <span class="fieldset-label">{{ __('Erfassungsart') }}</span>
                <div class="join">
                    <input type="radio" name="_time_mode" value="duration"
                           data-time-mode-radio data-target="duration"
                           class="join-item btn btn-sm"
                           aria-label="{{ __('Dauer') }}"
                           @checked($initialMode === 'duration')>
                    <input type="radio" name="_time_mode" value="range"
                           data-time-mode-radio data-target="range"
                           class="join-item btn btn-sm"
                           aria-label="{{ __('Von / Bis') }}"
                           @checked($initialMode === 'range')>
                </div>
            </div>

            {{-- Dauer-Modus: nur Datum + HH:MM --}}
            <div class="fieldset" data-time-mode-pane="duration">
                <label for="date" class="fieldset-label">{{ __('Datum') }}</label>
                <input id="date" name="date" type="date"
                       class="input input-bordered w-full"
                       value="{{ old('date', $entry?->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                @error('date')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" data-time-mode-pane="duration">
                <label for="time-entry-duration-hhmm" class="fieldset-label">{{ __('Dauer (HH:MM)') }}</label>
                <input id="time-entry-duration-hhmm" type="text" data-time-hhmm
                       class="input input-bordered w-full"
                       pattern="^\d{1,2}:[0-5]\d$"
                       placeholder="1:30"
                       value="{{ $hh }}:{{ $mm }}">
                @error('minutes')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            {{-- Range-Modus: Von / Bis (nutzt die bestehende x-date-range-Komponente) --}}
            <div class="md:col-span-2" data-time-mode-pane="range">
                <x-date-range
                    type="datetime-local"
                    layout="join"
                    form-control
                    fromName="started_at"
                    toName="ended_at"
                    fromId="time-entry-started-at"
                    toId="time-entry-ended-at"
                    :from="old('started_at', $entry?->started_at?->orgTz()->format('Y-m-d\TH:i'))"
                    :to="old('ended_at', $entry?->ended_at?->orgTz()->format('Y-m-d\TH:i'))"
                    :label="__('Von / Bis')"
                    :fromLabel="__('Von')"
                    :toLabel="__('Bis')"
                    :fromError="$errors->first('started_at')"
                    :toError="$errors->first('ended_at')"
                />
            </div>

            <div class="fieldset" data-time-mode-pane="range">
                <label for="break_minutes" class="fieldset-label">{{ __('Pause (Minuten)') }}</label>
                <input id="break_minutes" name="break_minutes" type="number" min="0" max="600"
                       class="input input-bordered w-full"
                       value="{{ old('break_minutes', $entry?->break_minutes ?? 0) }}">
                @error('break_minutes')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        </x-form-group>

        <x-form-group :legend="__('Tätigkeit')" icon="description" tone="info">
            @if ($tasks->isNotEmpty())
                <x-select-field name="task_id" :label="__('Aufgabe (optional)')">
                    <option value="">{{ __('Keine Aufgabe') }}</option>
                    @foreach ($tasks as $t)
                        <option value="{{ $t->sqid }}" @selected((string) old('task_id', \App\Support\Sqid::encode(\App\Models\Task::class, $entry?->task_id)) === $t->sqid)>{{ $t->title }}</option>
                    @endforeach
                </x-select-field>
            @endif

            @if ($diaryOptions->isNotEmpty())
                <x-select-field name="diary_entry_id" :label="__('Auftrag (optional)')">
                    <option value="">{{ __('Kein Auftrag') }}</option>
                    @foreach ($diaryOptions as $d)
                        @php
                            $label = $d->title ?: \Illuminate\Support\Str::limit((string) $d->content, 60);
                            if ($d->mode && $d->mode !== \App\Enums\Diary\Mode::Fixed) {
                                $label .= ' · ' . $d->modeLabel();
                            }
                        @endphp
                        <option value="{{ $d->sqid }}" @selected((string) old('diary_entry_id', \App\Support\Sqid::encode(\App\Models\DiaryEntry::class, $entry?->diary_entry_id)) === $d->sqid)>{{ $label }}</option>
                    @endforeach
                </x-select-field>
            @endif

            <x-input-field name="description"
                           :label="__('Beschreibung')"
                           type="text"
                           value="{{ old('description', $entry?->description) }}"
                           maxlength="500" />

            <div class="fieldset">
                <span class="fieldset-label">{{ __('Tags') }}</span>
                <x-tag-picker :tags="$allTags" :selected="$selectedTagIds" :recent="$recentTagIds" />
            </div>

            {{-- Nacharbeit/Kulanz (Feature 014, Rang 59): nur anzeigen, wenn Gründe gepflegt sind. --}}
            @if ($reworkOptions->isNotEmpty())
                <x-select-field name="rework_reason_classification_id" :label="__('Nacharbeitsgrund')">
                    <option value="">{{ __('— keiner —') }}</option>
                    @foreach ($reworkOptions as $option)
                        <option value="{{ $option->sqid }}" @selected((string) old('rework_reason_classification_id', \App\Support\Sqid::encode(\App\Models\Classification::class, $entry?->rework_reason_classification_id)) === $option->sqid)>{{ $option->label }}</option>
                    @endforeach
                </x-select-field>
            @endif
            @if ($goodwillOptions->isNotEmpty())
                <x-select-field name="goodwill_reason_classification_id" :label="__('Kulanzgrund')">
                    <option value="">{{ __('— keiner —') }}</option>
                    @foreach ($goodwillOptions as $option)
                        <option value="{{ $option->sqid }}" @selected((string) old('goodwill_reason_classification_id', \App\Support\Sqid::encode(\App\Models\Classification::class, $entry?->goodwill_reason_classification_id)) === $option->sqid)>{{ $option->label }}</option>
                    @endforeach
                </x-select-field>
            @endif
        </x-form-group>

        @if (($travelFlatMinutes ?? 0) > 0)
            <x-form-group :legend="__('customer-billing.travel_flat')" icon="directions_car" tone="info" cols="1">
                <div class="fieldset">
                    <label class="fieldset-label" for="billing_travel_minutes">{{ __('customer-billing.travel_minutes_entry') }}</label>
                    <input type="number" id="billing_travel_minutes" name="billing_travel_minutes" min="0" max="480"
                           placeholder="{{ $travelFlatMinutes }}"
                           value="{{ old('billing_travel_minutes', $entry?->billing_travel_manual ? $entry->billing_travel_minutes : null) }}"
                           class="input input-bordered w-full">
                    <p class="text-xs text-muted mt-1">
                        {{ __('customer-billing.travel_minutes_entry_hint', ['minutes' => $travelFlatMinutes]) }}
                    </p>
                </div>
            </x-form-group>
        @endif

        @include('time-entries._edit_extras', ['entry' => $entry])
</x-modal>
