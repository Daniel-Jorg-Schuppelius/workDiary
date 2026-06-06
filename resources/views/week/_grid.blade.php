@php
    /**
     * Partial: Wochen-Grid für eine einzelne ISO-Woche.
     *
     * Erwartete Variablen (über @include übergeben):
     * - $wv             : array{key,isoWeek,isoYear,start,end,days,shiftsByDay,assignmentsByDay,entriesByDay,rangeLabel,shortLabel}
     * - $service        : App\Services\Calendar\WeekViewService
     * - $holidays       : App\Services\HolidayService
     * - $hours          : array<int, int>
     * - $statusToneClass: array<string, string>
     * - $workHoursCfg   : array (aus config('app.work_hours'))
     * - $bands          : array{coreTop,coreBottom,extTop,extBottom,coreDays,extDays}
     */
    $weekStart = $wv['start'];
    $days = $wv['days'];
    $shiftsByDay = $wv['shiftsByDay'];
    $assignmentsByDay = $wv['assignmentsByDay'];
    $entriesByDay = $wv['entriesByDay'];
    $coreTop = $bands['coreTop'];
    $coreBottom = $bands['coreBottom'];
    $extTop = $bands['extTop'];
    $extBottom = $bands['extBottom'];
    $coreDays = $bands['coreDays'];
    $extDays = $bands['extDays'];
@endphp

<div class="wd-week-scroll min-h-0 flex-1 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
    <div class="wd-week-grid" data-week-key="{{ $wv['key'] }}">
        {{-- Header row --}}
        <div class="wd-week-corner"></div>
        @foreach ($days as $dayIndex => $day)
            @php
                $isToday = $day->isSameDay(now());
                $isWeekend = $day->isoWeekday() >= 6;
                $isSunday = (int) $day->isoWeekday() === 7;
                $holidayName = isset($holidays) ? $holidays->nameFor($day) : null;
                $isHoliday = $holidayName !== null;
            @endphp
            <div class="wd-week-day-header {{ $isToday ? 'is-today' : '' }} {{ $isWeekend ? 'is-weekend' : '' }} {{ $isSunday ? 'is-sunday' : '' }} {{ $isHoliday ? 'is-holiday' : '' }}"
                 @if ($isHoliday) title="{{ $holidayName }}" @endif>
                <div class="text-xs uppercase tracking-[0.2em] text-base-content/60">{{ $day->isoFormat('dd') }}</div>
                <div class="font-['Space_Grotesk'] text-lg">{{ $day->isoFormat('DD.MM.') }}</div>
                @if ($isHoliday)
                    <div class="mt-0.5 truncate text-[0.65rem] font-medium uppercase tracking-wider text-error">{{ $holidayName }}</div>
                @endif
            </div>
        @endforeach

        {{-- Hour axis --}}
        <div class="wd-week-hours">
            @foreach ($hours as $h)
                <div class="wd-week-hour-label">{{ sprintf('%02d:00', $h) }}</div>
            @endforeach
        </div>

        {{-- Day columns --}}
        @foreach ($days as $dayIndex => $day)
            @php
                $isToday = $day->isSameDay(now());
                $iso = (int) $day->isoWeekday();
                $dayHolidayName = isset($holidays) ? $holidays->nameFor($day) : null;
                $dayIsHoliday = $dayHolidayName !== null;
                $hasExt = ! $dayIsHoliday && in_array($iso, $extDays, true);
                $hasCore = ! $dayIsHoliday && in_array($iso, $coreDays, true);
            @endphp
            <div class="wd-week-day {{ $isToday ? 'is-today' : '' }}" data-date="{{ $day->format('Y-m-d') }}">
                {{-- Arbeitszeit-Bänder (Hintergrund: außerhalb der Kernzeit grau) --}}
                @if ($hasExt || $hasCore)
                    @php
                        $extT = $hasExt ? $extTop : 0;
                        $extB = $hasExt ? $extBottom : 100;
                        $coreT = $hasCore ? $coreTop : $extT;
                        $coreB = $hasCore ? $coreBottom : $extB;
                    @endphp
                    @if ($extT > 0)
                        <div class="wd-week-band wd-week-band--off" style="top: 0; height: {{ $extT }}%"></div>
                    @endif
                    @if ($coreT > $extT)
                        <div class="wd-week-band wd-week-band--extended" style="top: {{ $extT }}%; height: {{ $coreT - $extT }}%"></div>
                    @endif
                    @if ($extB > $coreB)
                        <div class="wd-week-band wd-week-band--extended" style="top: {{ $coreB }}%; height: {{ $extB - $coreB }}%"></div>
                    @endif
                    @if ($extB < 100)
                        <div class="wd-week-band wd-week-band--off" style="top: {{ $extB }}%; height: {{ 100 - $extB }}%"></div>
                    @endif
                @else
                    <div class="wd-week-band wd-week-band--off" style="top: 0; height: 100%"></div>
                @endif

                {{-- background hour grid --}}
                @foreach ($hours as $h)
                    <div class="wd-week-hour-row" style="top: {{ ($h / 24) * 100 }}%"></div>
                @endforeach

                {{-- Shifts (left band) --}}
                @foreach ($shiftsByDay[$dayIndex] as $shift)
                    @php
                        $p = $service->placement($shift->start_at, $shift->end_at, $day);
                        $shiftTitle = __('Bereitschaft') . ' · ' . ($shift->user?->name ?? '—') . ' · ' . $shift->start_at->orgTz()->format('d.m. H:i') . '–' . $shift->end_at->ftime();
                    @endphp
                    @can('update', $shift)
                        <a href="{{ route('shifts.edit', $shift) }}"
                           data-entry-modal-trigger
                           class="wd-week-shift"
                           style="top: {{ $p['top'] }}%; height: {{ $p['height'] }}%"
                           title="{{ $shiftTitle }}">
                            <span class="wd-week-shift-label">{{ $shift->user?->name ?? '—' }}</span>
                        </a>
                    @else
                        <div class="wd-week-shift" style="top: {{ $p['top'] }}%; height: {{ $p['height'] }}%" title="{{ $shiftTitle }}">
                            <span class="wd-week-shift-label">{{ $shift->user?->name ?? '—' }}</span>
                        </div>
                    @endcan
                @endforeach

                {{-- Emergency assignments (markers) --}}
                @foreach ($assignmentsByDay[$dayIndex] as $assignment)
                    @php
                        $p = $service->placement($assignment->start_at, $assignment->end_at, $day);
                        $assignmentTitle = __('Notdienst') . ' · ' . ($assignment->user?->name ?? '—') . ' · ' . $assignment->start_at->orgTz()->format('d.m. H:i') . '–' . $assignment->end_at->ftime() . ($assignment->reason ? ' · ' . $assignment->reason : '');
                    @endphp
                    @can('update', $assignment)
                        <a href="{{ route('assignments.edit', $assignment) }}"
                           data-entry-modal-trigger
                           class="wd-week-emergency"
                           style="top: {{ $p['top'] }}%; height: {{ max($p['height'], 2.5) }}%"
                           title="{{ $assignmentTitle }}">
                            <span class="wd-week-emergency-label">⚡ {{ $assignment->user?->name ?? '—' }}</span>
                        </a>
                    @else
                        <div class="wd-week-emergency" style="top: {{ $p['top'] }}%; height: {{ max($p['height'], 2.5) }}%" title="{{ $assignmentTitle }}">
                            <span class="wd-week-emergency-label">⚡ {{ $assignment->user?->name ?? '—' }}</span>
                        </div>
                    @endcan
                @endforeach

                {{-- Diary entries (cards) --}}
                @foreach ($service->layoutEntries($entriesByDay[$dayIndex], $day) as $placed)
                    @php
                        $entry = $placed['entry'];
                        $toneClass = $statusToneClass[$entry->statusTone()] ?? 'wd-week-entry--neutral';
                        $userHue = $entry->user_id ? $service->userHue((int) $entry->user_id) : 210;
                        $leftFrac = $placed['left'] / 100;
                        $widthFrac = $placed['width'] / 100;
                    @endphp
                    <a href="{{ route('diary.show', $entry) }}" data-entry-modal-trigger
                       class="wd-week-entry {{ $toneClass }}"
                       style="top: {{ $placed['top'] }}%; height: {{ max($placed['height'], 3) }}%; left: calc(1rem + (100% - 1.25rem) * {{ $leftFrac }} + 2px); width: calc((100% - 1.25rem) * {{ $widthFrac }} - 4px); border-left: 3px solid hsl({{ $userHue }} 70% 45%);"
                       title="{{ $entry->statusLabel() }} · {{ $entry->user?->name }} · {{ $entry->start_at?->orgTz()->format('d.m. H:i') }}">
                        <span class="wd-week-entry-time">{{ $entry->start_at?->ftime() }}{{ $entry->user ? ' · ' . $entry->user->name : '' }}</span>
                        <span class="wd-week-entry-text">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->content, 60) }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
