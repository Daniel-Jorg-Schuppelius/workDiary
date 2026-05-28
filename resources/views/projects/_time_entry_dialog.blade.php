{{-- Erwartet: $project, $entry (null = neu), $tasks, $diaryOptions, $isDialog --}}
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
                <label class="fieldset-label">{{ __('Erfassungsart') }}</label>
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
                <label class="fieldset-label">{{ __('Datum') }}</label>
                <input name="date" type="date"
                       class="input input-bordered w-full"
                       value="{{ old('date', $entry?->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                @error('date')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>

            <div class="fieldset" data-time-mode-pane="duration">
                <label class="fieldset-label">{{ __('Dauer (HH:MM)') }}</label>
                <input type="text" data-time-hhmm
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
                    :from="old('started_at', $entry?->started_at?->format('Y-m-d\TH:i'))"
                    :to="old('ended_at', $entry?->ended_at?->format('Y-m-d\TH:i'))"
                    :label="__('Von / Bis')"
                    :fromLabel="__('Von')"
                    :toLabel="__('Bis')"
                    :fromError="$errors->first('started_at')"
                    :toError="$errors->first('ended_at')"
                />
            </div>

            <div class="fieldset" data-time-mode-pane="range">
                <label class="fieldset-label">{{ __('Pause (Minuten)') }}</label>
                <input name="break_minutes" type="number" min="0" max="600"
                       class="input input-bordered w-full"
                       value="{{ old('break_minutes', $entry?->break_minutes ?? 0) }}">
                @error('break_minutes')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        </x-form-group>

        <x-form-group :legend="__('Tätigkeit')" icon="description" tone="info">
            @if ($tasks->isNotEmpty())
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Aufgabe (optional)') }}</label>
                    <select name="task_id" class="select select-bordered w-full">
                        <option value="">{{ __('Keine Aufgabe') }}</option>
                        @foreach ($tasks as $t)
                            <option value="{{ $t->id }}" @selected(old('task_id', $entry?->task_id) == $t->id)>{{ $t->title }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($diaryOptions->isNotEmpty())
                <div class="fieldset">
                    <label class="fieldset-label">{{ __('Auftrag (optional)') }}</label>
                    <select name="diary_entry_id" class="select select-bordered w-full">
                        <option value="">{{ __('Kein Auftrag') }}</option>
                        @foreach ($diaryOptions as $d)
                            @php
                                $label = $d->title ?: \Illuminate\Support\Str::limit((string) $d->content, 60);
                                if ($d->mode && $d->mode !== \App\Enums\Diary\Mode::Fixed) {
                                    $label .= ' · ' . $d->modeLabel();
                                }
                            @endphp
                            <option value="{{ $d->sqid }}" @selected((string) old('diary_entry_id', sqid(\App\Models\DiaryEntry::class, $entry?->diary_entry_id)) === $d->sqid)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Beschreibung') }}</label>
                <input name="description" type="text" maxlength="500"
                       class="input input-bordered w-full"
                       value="{{ old('description', $entry?->description) }}">
            </div>
        </x-form-group>

        @include('time-entries._edit_extras', ['entry' => $entry])
</x-modal>
