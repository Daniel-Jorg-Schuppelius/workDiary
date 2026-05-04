@extends('layouts.app')
@section('title', __('Wochenansicht') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Wochenansicht'))
@section('wrapper-height-class', 'h-dvh overflow-clip')
@section('main-class', 'min-h-0 overflow-clip flex flex-col')

@php
    $weekNumber = $weekStart->isoWeek();
    $year = $weekStart->isoWeekYear();
    $rangeLabel = $weekStart->isoFormat('DD.MM.') . ' – ' . $weekStart->addDays(6)->isoFormat('DD.MM.YYYY');
    $hours = range(0, 23);
    $statusToneClass = [
        'done' => 'wd-week-entry--done',
        'progress' => 'wd-week-entry--progress',
        'open' => 'wd-week-entry--open',
        'alert' => 'wd-week-entry--alert',
        'neutral' => 'wd-week-entry--neutral',
    ];
    $workHours = config('app.work_hours');
    $toPct = static function (string $hhmm): float {
        [$h, $m] = array_pad(array_map('intval', explode(':', $hhmm)), 2, 0);
        return (($h * 60 + $m) / 1440) * 100;
    };
    $coreTop = $toPct($workHours['core']['start'] ?? '08:00');
    $coreBottom = $toPct($workHours['core']['end'] ?? '16:00');
    $extTop = $toPct($workHours['extended']['start'] ?? '06:00');
    $extBottom = $toPct($workHours['extended']['end'] ?? '19:00');
    $coreDays = $workHours['core']['days'] ?? [];
    $extDays = $workHours['extended']['days'] ?? [];
@endphp

@section('content')
<div class="wd-week flex h-full min-h-0 flex-col gap-4">
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('week.index', ['date' => $prevDate, 'scope' => $teamScope ? 'team' : 'mine']) }}"
               class="btn btn-sm btn-ghost" title="{{ __('Vorherige Woche') }}">«</a>
            <a href="{{ route('week.index', ['date' => $todayDate, 'scope' => $teamScope ? 'team' : 'mine']) }}"
               class="btn btn-sm btn-outline">{{ __('Heute') }}</a>
            <a href="{{ route('week.index', ['date' => $nextDate, 'scope' => $teamScope ? 'team' : 'mine']) }}"
               class="btn btn-sm btn-ghost" title="{{ __('Nächste Woche') }}">»</a>

            <form method="GET" action="{{ route('week.index') }}" class="flex items-center gap-2 ml-2">
                <input type="hidden" name="scope" value="{{ $teamScope ? 'team' : 'mine' }}">
                <input type="date" name="date" value="{{ $weekStart->toDateString() }}"
                       class="input input-bordered input-sm" onchange="this.form.submit()">
            </form>

            <span class="ml-3 font-['Space_Grotesk'] text-base-content">
                <span class="badge badge-primary badge-sm align-middle">{{ __('KW') }} {{ $weekNumber }} / {{ $year }}</span>
                <span class="ml-2 text-sm text-base-content/70">{{ $rangeLabel }}</span>
            </span>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" id="wd-week-fit-btn"
                    class="btn btn-sm btn-ghost gap-1.5"
                    title="{{ __('Ansicht an Bildschirm anpassen') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M3 4a1 1 0 011-1h3a1 1 0 010 2H5.414l2.293 2.293a1 1 0 01-1.414 1.414L4 6.414V8a1 1 0 01-2 0V4zm14 0a1 1 0 00-1-1h-3a1 1 0 000 2h1.586l-2.293 2.293a1 1 0 001.414 1.414L16 6.414V8a1 1 0 002 0V4zm-3 12a1 1 0 001-1v-3a1 1 0 00-2 0v1.586l-2.293-2.293a1 1 0 00-1.414 1.414L13.586 16H12a1 1 0 000 2h3zm-8 0a1 1 0 01-1-1v-3a1 1 0 012 0v1.586l2.293-2.293a1 1 0 011.414 1.414L6.414 16H8a1 1 0 010 2H5z"/>
                </svg>
                <span id="wd-week-fit-label">{{ __('Auf Bildschirm') }}</span>
            </button>

            <div class="join">
                <a href="{{ route('week.index', ['date' => $weekStart->toDateString(), 'scope' => 'mine']) }}"
                   class="join-item btn btn-sm {{ $teamScope ? 'btn-ghost' : 'btn-primary' }}">{{ __('Meine Woche') }}</a>
                <a href="{{ route('week.index', ['date' => $weekStart->toDateString(), 'scope' => 'team']) }}"
                   class="join-item btn btn-sm {{ $teamScope ? 'btn-primary' : 'btn-ghost' }}">{{ __('Team-Woche') }}</a>
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap items-center gap-4 rounded-box border border-base-300 bg-base-100 px-4 py-2 text-xs text-base-content/70 shadow-xs">
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-band--core"></span>{{ __('Kernarbeitszeit') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-band--extended"></span>{{ __('Erweiterte Arbeitszeit') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-shift"></span>{{ __('Bereitschaft') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-emergency"></span>{{ __('Notdienst') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-entry--open"></span>{{ __('Offen') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-entry--alert"></span>{{ __('Problem') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-entry--progress"></span>{{ __('Bestätigt') }}</span>
        <span class="inline-flex items-center gap-2"><span class="wd-week-legend wd-week-entry--done"></span>{{ __('Erledigt') }}</span>
    </div>

    {{-- User-Tabs (nur in Team-Woche) --}}
    @if ($teamScope && ($weekUsers ?? collect())->isNotEmpty())
        <div role="tablist" class="tabs tabs-box">
            <a role="tab"
               href="{{ route('week.index', ['date' => $weekStart->toDateString(), 'scope' => 'team']) }}"
               class="tab {{ ! $filterUserId ? 'tab-active' : '' }}">
                {{ __('Alle') }}
            </a>
            @foreach ($weekUsers as $u)
                @php
                    $hue = $service->userHue((int) $u->id);
                    $isActive = (int) $filterUserId === (int) $u->id;
                    $color = "hsl({$hue} 70% 45%)";
                    $soft = "hsl({$hue} 70% 92%)";
                @endphp
                <a role="tab"
                   href="{{ route('week.index', ['date' => $weekStart->toDateString(), 'scope' => 'team', 'user' => $u->id]) }}"
                   class="tab gap-2 {{ $isActive ? 'tab-active' : '' }}"
                   style="--tab-bg: {{ $soft }}; --tab-border-color: {{ $color }}; {{ $isActive ? 'color: ' . $color . ';' : '' }}">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $color }};"></span>
                    <span>{{ $u->name }}</span>
                </a>
            @endforeach
        </div>
    @endif    {{-- Grid --}}
    <div id="wd-week-scroll" class="min-h-0 flex-1 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div id="wd-week-grid" class="wd-week-grid">
            {{-- Header row --}}
            <div class="wd-week-corner"></div>
            @foreach ($days as $day)
                @php
                    $isToday = $day->isSameDay(now());
                    $isWeekend = $day->isoWeekday() >= 6;
                    $holidayName = isset($holidays) ? $holidays->nameFor($day) : null;
                    $isHoliday = $holidayName !== null;
                @endphp
                <div class="wd-week-day-header {{ $isToday ? 'is-today' : '' }} {{ $isWeekend ? 'is-weekend' : '' }} {{ $isHoliday ? 'is-holiday' : '' }}"
                     @if ($isHoliday) title="{{ $holidayName }}" @endif>
                    <div class="text-xs uppercase tracking-[0.2em] text-base-content/60">{{ $day->isoFormat('dd') }}</div>
                    <div class="flex items-center justify-center gap-2 font-['Space_Grotesk'] text-lg">
                        <span>{{ $day->isoFormat('DD.MM.') }}</span>
                        @php
                            $dayDate = $day->format('Y-m-d');
                            $shiftStart = $dayDate . 'T08:00';
                            $shiftEnd = $dayDate . 'T17:00';
                        @endphp
                        <div class="dropdown dropdown-end">
                            <button type="button" tabindex="0" class="btn btn-xs btn-ghost btn-circle" title="{{ __('Neu anlegen') }}">+</button>
                            <ul tabindex="0" class="dropdown-content menu z-20 mt-1 w-44 rounded-box border border-base-300 bg-base-100 p-1 text-sm shadow">
                                <li><a href="{{ route('diary.create', ['date' => $shiftStart]) }}" data-entry-modal-trigger>{{ __('Tagebucheintrag') }}</a></li>
                                <li><a href="{{ route('shifts.create', ['start_at' => $shiftStart, 'end_at' => $shiftEnd]) }}" data-entry-modal-trigger>{{ __('Bereitschaft') }}</a></li>
                                <li><a href="{{ route('assignments.create', ['start_at' => $shiftStart, 'end_at' => $shiftEnd]) }}" data-entry-modal-trigger>{{ __('Notdienst') }}</a></li>
                            </ul>
                        </div>
                    </div>
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
                <div class="wd-week-day {{ $isToday ? 'is-today' : '' }}">
                    {{-- Arbeitszeit-Bänder (Hintergrund: außerhalb der Kernzeit grau) --}}
                    @if ($hasExt || $hasCore)
                        @php
                            $extT = $hasExt ? $extTop : 0;
                            $extB = $hasExt ? $extBottom : 100;
                            $coreT = $hasCore ? $coreTop : $extT;
                            $coreB = $hasCore ? $coreBottom : $extB;
                        @endphp
                        {{-- vor erweiterter Zeit (dunkler) --}}
                        @if ($extT > 0)
                            <div class="wd-week-band wd-week-band--off" style="top: 0; height: {{ $extT }}%"></div>
                        @endif
                        {{-- erweitert vor Kern (heller) --}}
                        @if ($coreT > $extT)
                            <div class="wd-week-band wd-week-band--extended" style="top: {{ $extT }}%; height: {{ $coreT - $extT }}%"></div>
                        @endif
                        {{-- erweitert nach Kern (heller) --}}
                        @if ($extB > $coreB)
                            <div class="wd-week-band wd-week-band--extended" style="top: {{ $coreB }}%; height: {{ $extB - $coreB }}%"></div>
                        @endif
                        {{-- nach erweiterter Zeit (dunkler) --}}
                        @if ($extB < 100)
                            <div class="wd-week-band wd-week-band--off" style="top: {{ $extB }}%; height: {{ 100 - $extB }}%"></div>
                        @endif
                    @else
                        {{-- Kein Arbeitstag: ganzer Tag grau --}}
                        <div class="wd-week-band wd-week-band--off" style="top: 0; height: 100%"></div>
                    @endif

                    {{-- background hour grid --}}
                    @foreach ($hours as $h)
                        <div class="wd-week-hour-row" style="top: {{ ($h / 24) * 100 }}%"></div>
                    @endforeach

                    {{-- Shifts (left band) --}}
                    @foreach ($shiftsByDay[$dayIndex] as $shift)
                        @php $p = $service->placement($shift->start_at, $shift->end_at, $day); @endphp
                        <div class="wd-week-shift" style="top: {{ $p['top'] }}%; height: {{ $p['height'] }}%"
                             title="{{ __('Bereitschaft') }} · {{ $shift->user?->name }} · {{ $shift->start_at->format('d.m. H:i') }}–{{ $shift->end_at->format('H:i') }}">
                            <span class="wd-week-shift-label">{{ $shift->user?->name ?? '—' }}</span>
                        </div>
                    @endforeach

                    {{-- Emergency assignments (markers) --}}
                    @foreach ($assignmentsByDay[$dayIndex] as $assignment)
                        @php $p = $service->placement($assignment->start_at, $assignment->end_at, $day); @endphp
                        <div class="wd-week-emergency" style="top: {{ $p['top'] }}%; height: {{ max($p['height'], 2.5) }}%"
                             title="{{ __('Notdienst') }} · {{ $assignment->user?->name }} · {{ $assignment->start_at->format('d.m. H:i') }}–{{ $assignment->end_at->format('H:i') }}{{ $assignment->reason ? ' · ' . $assignment->reason : '' }}">
                            <span class="wd-week-emergency-label">⚡ {{ $assignment->user?->name ?? '—' }}</span>
                        </div>
                    @endforeach

                    {{-- Diary entries (cards) --}}
                    @foreach ($service->layoutEntries($entriesByDay[$dayIndex], $day) as $placed)
                        @php
                            $entry = $placed['entry'];
                            $toneClass = $statusToneClass[$entry->statusTone()] ?? 'wd-week-entry--neutral';
                            // Stabile Farb-Akzentuierung pro Benutzer (Hue 0–359)
                            $userHue = $entry->user_id ? $service->userHue((int) $entry->user_id) : 210;
                            // Lane-Geometrie: linke Spurzone für die Bereitschaft (1.25rem) reservieren
                            $leftFrac = $placed['left'] / 100;
                            $widthFrac = $placed['width'] / 100;
                        @endphp
                        <a href="{{ route('diary.show', $entry) }}" data-entry-modal-trigger
                           class="wd-week-entry {{ $toneClass }}"
                           style="top: {{ $placed['top'] }}%; height: {{ max($placed['height'], 3) }}%; left: calc(1rem + (100% - 1.25rem) * {{ $leftFrac }} + 2px); width: calc((100% - 1.25rem) * {{ $widthFrac }} - 4px); border-left: 3px solid hsl({{ $userHue }} 70% 45%);"
                           title="{{ $entry->statusLabel() }} · {{ $entry->user?->name }} · {{ $entry->start_at?->format('d.m. H:i') }}">
                            <span class="wd-week-entry-time">{{ $entry->start_at?->format('H:i') }}{{ $entry->user ? ' · ' . $entry->user->name : '' }}</span>
                            <span class="wd-week-entry-text">{{ \Illuminate\Support\Str::limit($entry->content, 60) }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
(function () {
    var scroll = document.getElementById('wd-week-scroll');
    var grid   = document.getElementById('wd-week-grid');
    var btn    = document.getElementById('wd-week-fit-btn');
    var label  = document.getElementById('wd-week-fit-label');
    var KEY    = 'workDiaryWeekCalFit';
    var fit    = localStorage.getItem(KEY) === '1';

    function hourHeight() {
        if (!scroll) return null;
        // Verfügbare Höhe = Scroll-Container - Header-Zeile (sticky, ~erste Kind-Höhe)
        var firstHeader = grid ? grid.querySelector('.wd-week-day-header') : null;
        var headerH = firstHeader ? firstHeader.offsetHeight : 48;
        var available = scroll.clientHeight - headerH;
        return Math.max(available / 24, 20);
    }

    function apply(fitMode) {
        if (!scroll || !grid) return;
        if (fitMode) {
            var h = hourHeight();
            grid.style.setProperty('--wd-hour-h', h + 'px');
            scroll.style.overflow = 'hidden';
            if (btn) { btn.classList.add('btn-primary'); btn.classList.remove('btn-ghost'); }
            if (label) label.textContent = '{{ __('Freies Scrollen') }}';
        } else {
            grid.style.removeProperty('--wd-hour-h');
            scroll.style.overflow = '';
            if (btn) { btn.classList.remove('btn-primary'); btn.classList.add('btn-ghost'); }
            if (label) label.textContent = '{{ __('Auf Bildschirm') }}';
        }
    }

    apply(fit);

    if (btn) {
        btn.addEventListener('click', function () {
            fit = !fit;
            localStorage.setItem(KEY, fit ? '1' : '0');
            apply(fit);
        });
    }

    window.addEventListener('resize', function () {
        if (fit) apply(true);
    });
})();
</script>
@endsection
