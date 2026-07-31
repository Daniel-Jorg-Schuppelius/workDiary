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
                    'recent' => $entryBarRecentIds->contains($p->id),
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
              {{-- Kein space-y am Formular: es würde der sichtbaren Hauptzeile
                   8px margin-bottom geben, weil das LETZTE Kind (Zuordnung) meist
                   display:none ist — die Zweitzeile bringt ihr mt-2 selbst mit. --}}
              :action="formAction">
            @csrf
            <input type="hidden" name="project_id" :value="selectedId" value="{{ old('project_id') }}">

            {{-- Alles in einer Zeile (bricht nur auf schmalen Screens um). --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- basis-40: schmale Plan-Breite, damit die Zeile nicht wegen der
                     Browser-Default-Breite des Inputs umbricht; flex-1 füllt danach. --}}
                <input type="text" name="description" maxlength="500"
                       class="input input-bordered input-sm min-w-40 basis-40 flex-1"
                       placeholder="{{ __('Woran arbeitest du?') }}"
                       value="{{ old('description') }}">

                {{-- Projekt-Combobox: tippen filtert (zuletzt genutzte zuerst). --}}
                <div class="relative w-full sm:w-48" @click.outside="closeMenu()">
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
                    {{-- Ohne Suchtext gruppiert: „Zuletzt verwendet" (Top 10) und
                         „Weitere Projekte"; mit Suchtext eine flache Trefferliste
                         (otherFiltered ist dann leer). `flex flex-col` explizit:
                         daisyUI-5-Menüeinträge sind sonst Grid mit Spalten-Autoflow
                         → Projekt und Kunde würden nebeneinander gequetscht. --}}
                    <ul x-show="showMenu" x-cloak x-transition.opacity
                        class="menu menu-sm absolute z-30 mt-1 w-full max-h-72 flex-nowrap overflow-y-auto rounded-box border border-base-300 bg-base-100 shadow-lg">
                        <li class="menu-title" x-show="showRecentLabel">{{ __('Zuletzt verwendet') }}</li>
                        <template x-for="(p, idx) in primaryFiltered" :key="p.id">
                            <li>
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
                        <li class="menu-title" x-show="showOtherLabel">{{ __('Weitere Projekte') }}</li>
                        <template x-for="(p, idx) in otherFiltered" :key="p.id">
                            <li>
                                <button type="button"
                                        class="flex flex-col items-start gap-1 py-2"
                                        :class="optionClassOther(idx)"
                                        @mouseenter="setHighlightOther(idx)"
                                        @click="choose(p)">
                                    <span class="font-medium leading-tight" x-text="p.name"></span>
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

                {{-- EIN Dreifach-Segment für den Eingabemodus (einzeilige Leiste):
                     Timer | Dauer | Von/Bis. Dauer/Von-Bis schalten zugleich auf
                     Manuell um. Nur mit JS nutzbar (x-cloak). --}}
                <div class="join" x-cloak>
                    @if ($isToday)
                        <button type="button" class="btn btn-sm join-item" :class="timerBtnClass"
                                @click="setModeTimer">{{ __('Timer') }}</button>
                    @endif
                    <button type="button" class="btn btn-sm join-item" :class="durationBtnClass"
                            @click="setDuration">{{ __('Dauer') }}</button>
                    <button type="button" class="btn btn-sm join-item" :class="rangeBtnClass"
                            @click="setRange">{{ __('Von / Bis') }}</button>
                </div>

                {{-- fieldset statt x-show/:disabled am Input: flatpickr (altInput)
                     ersetzt das Feld durch ein sichtbares Zwillings-Input im selben
                     Elternknoten — nur der umschließende Wrapper blendet beide
                     zusammen aus, und nur ein disabled-fieldset deaktiviert auch
                     das Zwillingsfeld (dynamisches :disabled am Original erreicht
                     es nicht). --}}
                {{-- Ketten-Erfassung: nach einer Von/Bis-Buchung ist das Datum mit
                     dem Ende der letzten Buchung vorbelegt (Session-Flash aus dem
                     Store) — bei Buchungen über Mitternacht also der Folgetag. --}}
                <fieldset class="inline-flex" x-show="isManual" :disabled="manualDisabled">
                    <input type="date" name="date" class="input input-bordered input-sm w-32"
                           aria-label="{{ __('Datum') }}"
                           value="{{ old('date', session('entryBar.nextDate', $day->toDateString())) }}">
                </fieldset>

                <input type="text" inputmode="numeric" placeholder="{{ __('Dauer (HH:MM)') }}"
                       x-show="showDurationPane"
                       x-model="hhmm" @input="onHhmmInput"
                       :disabled="durationDisabled"
                       class="input input-bordered input-sm w-28 text-right tabular-nums"
                       aria-label="{{ __('Dauer (HH:MM)') }}">
                <input type="hidden" name="minutes" :value="minutes" :disabled="durationDisabled" value="{{ old('minutes') }}">

                {{-- Von/Bis über die Standard-Komponente x-date-range (type=time).
                     fieldset statt :disabled an den Einzel-Inputs: die Komponente
                     reicht Alpine-Bindings nicht durch — das fieldset deaktiviert
                     alle enthaltenen Felder auf einmal (inaktiver Modus submittet
                     nicht). :linked="false", weil Bis ≤ Von bewusst erlaubt ist
                     (Buchung über Mitternacht rollt serverseitig auf den Folgetag). --}}
                <fieldset class="inline-flex items-center gap-2" x-show="showRangePane" :disabled="rangeDisabled">
                    {{-- „Von" mit dem Ende der letzten Buchung vorbelegen (Kette). --}}
                    <x-date-range type="time"
                                  fromName="start_time" toName="end_time"
                                  :from="old('start_time', session('entryBar.nextStart'))" :to="old('end_time')"
                                  :label="false" :linked="false"
                                  size="sm" class="w-40" />
                    <div class="join">
                        <span class="join-item flex items-center border border-base-300 bg-base-200 px-2 text-xs text-base-content/60"
                              title="{{ __('Pause (Minuten)') }}">{{ __('Pause') }}</span>
                        <input type="number" name="break_minutes" min="0" max="600"
                               class="input input-bordered input-sm join-item w-14 px-1 text-right tabular-nums"
                               aria-label="{{ __('Pause (Minuten)') }}"
                               value="{{ old('break_minutes', 0) }}">
                    </div>
                </fieldset>

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
            <div class="mt-2 flex flex-wrap items-center gap-2" x-cloak x-show="moreOpen">
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
                <x-tag-picker class="w-full sm:w-96" :tags="$allTags ?? []" :selected="[]" :recent="$recentTagIds ?? []" />
            </div>
        </form>
    @endif
</x-card>
