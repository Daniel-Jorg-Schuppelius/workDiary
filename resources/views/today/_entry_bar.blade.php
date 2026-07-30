{{-- Eingabeleiste (Toggl-artig) — erwartet: $runningEntry (TimeEntry|null),
     $entryBarProjects (Collection<Project> mit customer), $day, $isToday.
     Einzeilig: Beschreibung + Projekt + (manuelle Zeitfelder) + Modus + Submit.
     Timer-Modus postet auf stopwatch.start, Manuell auf today.entry-bar.store;
     ohne JS gilt das statische action-Attribut (Manuell). --}}
<x-card as="section" data-entry-bar>
    @if ($runningEntry)
        <div class="flex flex-wrap items-center gap-3"
             x-data="stopwatch('{{ $runningEntry->started_at?->toIso8601String() }}')">
            <span class="relative flex size-2.5">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                <span class="relative inline-flex size-2.5 rounded-full bg-primary"></span>
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate font-['Space_Grotesk'] text-sm font-semibold">
                    {{ $runningEntry->project?->name ?? __('Läuft…') }}
                </p>
                @if ($runningEntry->description)
                    <p class="truncate text-xs text-base-content/60">{{ $runningEntry->description }}</p>
                @endif
            </div>
            <span class="font-['Space_Grotesk'] text-2xl font-semibold tabular-nums text-primary"
                  x-text="display">00:00:00</span>
            <form method="POST" action="{{ route('stopwatch.stop') }}" class="leading-none">
                @csrf
                <x-button type="submit" tone="error" size="sm" icon="stop_circle">{{ __('Stoppen') }}</x-button>
            </form>
        </div>
    @else
        @php
            $entryBarConfig = [
                'projects' => $entryBarProjects->map(fn ($p) => [
                    'id' => $p->sqid,
                    'name' => $p->name,
                    'customer' => $p->customer?->name,
                ])->values()->all(),
                'optionsUrl' => route('today.entry-bar.options', ['project' => '__ID__']),
                'startUrl' => route('stopwatch.start'),
                'storeUrl' => route('today.entry-bar.store'),
                'isToday' => (bool) $isToday,
                'selectedId' => old('project_id'),
                'taskId' => old('task_id'),
                'diaryEntryId' => old('diary_entry_id'),
                'minutes' => old('minutes'),
            ];
        @endphp
        <form method="POST" action="{{ route('today.entry-bar.store') }}"
              x-data="entryBar"
              data-config="{{ json_encode($entryBarConfig) }}"
              :action="formAction"
              class="space-y-2">
            @csrf
            <input type="hidden" name="project_id" :value="selectedId" value="{{ old('project_id') }}">

            {{-- Alles in einer Zeile (bricht nur auf schmalen Screens um). --}}
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" name="description" maxlength="500"
                       class="input input-bordered input-sm min-w-40 flex-1"
                       placeholder="{{ __('Woran arbeitest du?') }}"
                       value="{{ old('description') }}">

                {{-- Projekt-Combobox: tippen filtert (zuletzt genutzte zuerst). --}}
                <div class="relative w-full sm:w-56" @click.outside="closeMenu()">
                    <input type="text"
                           x-model="query"
                           @focus="openMenu()"
                           @input="onInput()"
                           @keydown.enter.prevent="enterPressed()"
                           @keydown.arrow-down.prevent="move(1)"
                           @keydown.arrow-up.prevent="move(-1)"
                           @keydown.escape="closeMenu()"
                           autocomplete="off"
                           class="input input-bordered input-sm w-full"
                           placeholder="{{ __('Projekt suchen…') }}">
                    <ul x-show="showMenu" x-cloak x-transition.opacity
                        class="menu menu-sm absolute z-30 mt-1 w-full max-h-64 flex-nowrap overflow-y-auto rounded-box border border-base-300 bg-base-100 shadow-lg">
                        <template x-for="(p, idx) in filtered" :key="p.id">
                            <li>
                                {{-- `flex` explizit: daisyUI-5-Menüeinträge sind sonst
                                     Grid mit Spalten-Autoflow → Projekt und Kunde würden
                                     nebeneinander gequetscht statt gestapelt. --}}
                                <button type="button"
                                        class="flex flex-col items-start gap-1 py-2"
                                        :class="optionClass(idx)"
                                        @mouseenter="setHighlight(idx)"
                                        @click="choose(p)">
                                    <span class="font-medium leading-tight" x-text="p.name"></span>
                                    {{-- opacity statt fester Textfarbe: bleibt auf dem
                                         menu-active-Hintergrund der Markierung lesbar. --}}
                                    <span class="text-xs leading-tight opacity-60" x-show="p.customer" x-text="p.customer"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p x-show="showEmpty" x-cloak
                       class="absolute z-30 mt-1 w-full rounded-box border border-base-300 bg-base-100 px-3 py-2 text-sm text-base-content/60 shadow-lg">
                        {{ __('Kein Projekt gefunden.') }}
                    </p>
                </div>

                {{-- Manuelle Zeitfelder, inline (inaktive Eingaben disabled → kein Submit). --}}
                <div class="join" x-cloak x-show="isManual">
                    <button type="button" class="btn btn-sm join-item" :class="durationBtnClass"
                            @click="setDuration">{{ __('Dauer') }}</button>
                    <button type="button" class="btn btn-sm join-item" :class="rangeBtnClass"
                            @click="setRange">{{ __('Von / Bis') }}</button>
                </div>

                {{-- Wrapper statt x-show am Input: flatpickr (altInput) ersetzt das
                     Feld durch ein sichtbares Zwillings-Input im selben Elternknoten —
                     nur ein umschließendes x-show blendet beide zusammen aus. --}}
                <span class="inline-flex" x-show="isManual">
                    <input type="date" name="date" class="input input-bordered input-sm w-36"
                           :disabled="manualDisabled"
                           aria-label="{{ __('Datum') }}"
                           value="{{ old('date', $day->toDateString()) }}">
                </span>

                <input type="text" inputmode="numeric" placeholder="{{ __('Dauer (HH:MM)') }}"
                       x-show="showDurationPane"
                       x-model="hhmm" @input="onHhmmInput"
                       :disabled="durationDisabled"
                       class="input input-bordered input-sm w-28 text-right tabular-nums"
                       aria-label="{{ __('Dauer (HH:MM)') }}">
                <input type="hidden" name="minutes" :value="minutes" :disabled="durationDisabled" value="{{ old('minutes') }}">

                <div class="flex items-center gap-1" x-show="showRangePane">
                    <input type="time" name="start_time" class="input input-bordered input-sm w-24"
                           :disabled="rangeDisabled"
                           aria-label="{{ __('Von') }}"
                           value="{{ old('start_time') }}">
                    <span class="text-base-content/60">–</span>
                    <input type="time" name="end_time" class="input input-bordered input-sm w-24"
                           :disabled="rangeDisabled"
                           aria-label="{{ __('Bis') }}"
                           value="{{ old('end_time') }}">
                    <input type="number" name="break_minutes" min="0" max="600"
                           :disabled="rangeDisabled"
                           class="input input-bordered input-sm w-16 px-2 text-right tabular-nums"
                           title="{{ __('Pause (Minuten)') }}"
                           aria-label="{{ __('Pause (Minuten)') }}"
                           value="{{ old('break_minutes', 0) }}">
                </div>

                {{-- Modus-Umschalter (nur mit JS nutzbar). --}}
                @if ($isToday)
                    <div class="join" x-cloak>
                        <button type="button" class="btn btn-sm join-item" :class="timerBtnClass"
                                @click="setModeTimer">{{ __('Timer') }}</button>
                        <button type="button" class="btn btn-sm join-item" :class="manualBtnClass"
                                @click="setModeManual">{{ __('Manuell') }}</button>
                    </div>
                @endif

                <button type="button" class="btn btn-sm btn-ghost btn-square" x-cloak x-show="hasProject"
                        @click="toggleMore"
                        title="{{ __('Weitere Felder') }}" aria-label="{{ __('Weitere Felder') }}">
                    <span class="material-symbols-outlined" aria-hidden="true" x-text="moreChevron">expand_more</span>
                </button>

                <x-button type="submit" tone="primary" size="sm" class="gap-1">
                    <span class="flex items-center gap-1" x-cloak x-show="isTimer">
                        <x-icon name="play_arrow" filled /> {{ __('Timer starten') }}
                    </span>
                    <span class="flex items-center gap-1" x-show="isManual">
                        <x-icon name="add_task" /> {{ __('Erfassen') }}
                    </span>
                </x-button>
            </div>

            {{-- Sekundärfelder: projektabhängig (Fetch nach Projektwahl). --}}
            <div class="flex flex-wrap items-center gap-2" x-cloak x-show="moreOpen">
                <label class="w-full text-xs uppercase tracking-[0.18em] text-base-content/60 sm:w-auto" x-show="hasSecondary">{{ __('Zuordnung') }}</label>
                <select name="task_id" x-model="taskId" x-show="hasTasks"
                        class="select select-bordered select-sm w-full sm:w-56"
                        aria-label="{{ __('Aufgabe (optional)') }}">
                    <option value="">{{ __('Keine Aufgabe') }}</option>
                    <template x-for="t in tasks" :key="t.id">
                        <option :value="t.id" x-text="t.title"></option>
                    </template>
                </select>
                <select name="diary_entry_id" x-model="diaryEntryId" x-show="hasDiary"
                        class="select select-bordered select-sm w-full sm:w-64"
                        aria-label="{{ __('Auftrag (optional)') }}">
                    <option value="">{{ __('Kein Auftrag') }}</option>
                    <template x-for="d in diaryEntries" :key="d.id">
                        <option :value="d.id" x-text="d.label"></option>
                    </template>
                </select>
                <p class="text-xs text-base-content/60" x-show="noSecondary">{{ __('Für dieses Projekt gibt es keine Aufgaben oder Aufträge.') }}</p>
            </div>
        </form>
    @endif
</x-card>
