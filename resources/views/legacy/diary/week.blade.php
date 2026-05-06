@extends('layouts.app')
@section('title', 'Wochenansicht — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Wochenansicht') . ' (Legacy)')
{{-- Full-viewport-height: kein Seiten-Scroll, nur interner Tabellen-Scroll --}}
@section('wrapper-height-class', 'h-dvh overflow-clip')
@section('main-class', 'min-h-0 overflow-clip flex flex-col')

@section('content')
@php
    /* @var \Carbon\Carbon $monday */
    /* @var \Carbon\Carbon $sunday */
    $weekStart = $monday ?? now()->startOfWeek(\Carbon\Carbon::MONDAY);
    $dayAbbr = weekdayAbbr('de');
    $days    = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));
    $hours   = range(7, 20); // Slots 07–08 bis 20–21
    $legacyUsers = collect($users ?? []);
@endphp

{{-- Toolbar --}}
<div class="flex-none flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('legacy.diary.week', ['week' => $weekOffset - 1]) }}"
           class="btn btn-sm btn-ghost" title="1 Woche zurück">«</a>
        <a href="{{ route('legacy.diary.week') }}"
           class="btn btn-sm btn-outline">{{ __('Heute') }}</a>
        <a href="{{ route('legacy.diary.week', ['week' => $weekOffset + 1]) }}"
           class="btn btn-sm btn-ghost" title="1 Woche vor">»</a>

        <form method="GET" action="{{ route('legacy.diary.week') }}" class="flex items-center gap-2 ml-2">
            <input type="week" name="week_date" value="{{ $selectedWeek ?? $monday->format('o-\\WW') }}"
                   class="input input-bordered input-sm" onchange="this.form.submit()">
        </form>

        <span class="ml-3 font-['Space_Grotesk'] text-base-content">
            <span class="text-sm text-base-content/70">
                {{ $monday->format('d.m.Y') }} &ndash; {{ $sunday->format('d.m.Y') }}
            </span>
        </span>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        {{-- Legende --}}
        <div class="flex flex-wrap items-center gap-3 text-xs text-base-content/60">
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-3 w-3 rounded-xs bg-info/30 outline-info outline-1"></span>{{ __('Bereitschaft') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-3 w-3 rounded-xs bg-warning/30 outline-warning outline-1"></span>{{ __('Notdienst') }}
            </span>
        </div>

        {{-- Fit-to-screen Toggle --}}
        <button type="button" id="week-fit-toggle"
                class="btn btn-sm btn-ghost gap-1.5"
                title="{{ __('Ansicht an Bildschirm anpassen') }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                <path d="M3 4a1 1 0 011-1h3a1 1 0 010 2H5.414l2.293 2.293a1 1 0 01-1.414 1.414L4 6.414V8a1 1 0 01-2 0V4zm14 0a1 1 0 00-1-1h-3a1 1 0 000 2h1.586l-2.293 2.293a1 1 0 001.414 1.414L16 6.414V8a1 1 0 002 0V4zm-3 12a1 1 0 001-1v-3a1 1 0 00-2 0v1.586l-2.293-2.293a1 1 0 00-1.414 1.414L13.586 16H12a1 1 0 000 2h3zm-8 0a1 1 0 01-1-1v-3a1 1 0 012 0v1.586l2.293-2.293a1 1 0 011.414 1.414L6.414 16H8a1 1 0 010 2H5z"/>
            </svg>
            <span id="week-fit-label">{{ __('Auf Bildschirm') }}</span>
        </button>
    </div>
</div>

{{-- Card mit Scroll-Container --}}
<div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs mt-4">
    <div id="week-scroll" class="h-full overflow-auto">
        @php
            /* min-width: Zeitspalte (4.5rem) + N Benutzer × 6rem + letzte Spalte (4.5rem) */
            $tableMinWidth = 'calc(4.5rem + ' . $legacyUsers->count() . ' * 6rem + 4.5rem)';
        @endphp
        <table id="week-table" class="week-table" data-min-width="{{ $tableMinWidth }}">
            <tbody>
            @foreach ($days as $dayIndex => $day)
                @php
                    $dateKey   = $day->format('Y-m-d');
                    $isToday   = $day->isToday();
                    $isSunday  = (int) $day->dayOfWeek === 0;
                    $isSaturday = (int) $day->dayOfWeek === 6;
                    if ($isToday)        $dayTh = 'kheute';
                    elseif ($isSunday)   $dayTh = 'kso';
                    elseif ($isSaturday) $dayTh = 'ksa';
                    else                 $dayTh = 'kopf';
                @endphp

                {{-- Tages-Kopfzeile: alle th sticky top + Ecke zusätzlich sticky left --}}
                <tr>
                    <th class="{{ $dayTh }} sticky top-0 left-0 z-20">{{ $day->format('d.m.y') }}</th>
                    @foreach ($legacyUsers as $legacyUser)
                        @php
                            $uid          = (int) $legacyUser->id;
                            $hasOncall    = isset($oncallByUserDay[$uid][$dateKey]);
                            $hasNotdienst = ! $hasOncall && isset($notdienstByUserDay[$uid][$dateKey]);
                            if ($hasOncall)        $userTh = 'mitb';
                            elseif ($hasNotdienst) $userTh = 'mitn';
                            else                   $userTh = 'mit';
                        @endphp
                        <th colspan="2" class="{{ $userTh }} sticky top-0 z-10" title="{{ $legacyUser->uname }}">{{ $legacyUser->uname }}</th>
                    @endforeach
                    <th class="{{ $dayTh }} sticky top-0 z-10">{{ $dayAbbr[$dayIndex] }}</th>
                </tr>

                {{-- Stundenzeilen 07–21 --}}
                @foreach ($hours as $hourIndex => $hour)
                    @php
                        $bg        = $hourIndex % 2 === 0;
                        $hourStart = $day->copy()->setTime($hour, 0, 0);
                        $hourEnd   = $day->copy()->setTime($hour + 1, 0, 0);
                        $hourLabel = sprintf('%02d–%02d', $hour, $hour + 1);
                    @endphp
                    <tr>
                        {{-- Zeitspalte: sticky links --}}
                        <td class="{{ $bg ? 'grau' : 'mitte' }} sticky left-0 z-10">{{ $hourLabel }}</td>

                        @foreach ($legacyUsers as $legacyUser)
                            @php
                                $uid          = (int) $legacyUser->id;
                                $hasOncall    = isset($oncallByUserDay[$uid][$dateKey]);
                                $hasNotdienst = ! $hasOncall && isset($notdienstByUserDay[$uid][$dateKey]);

                                $entry = null;
                                foreach (($entriesByUserDay[$uid][$dateKey] ?? []) as $e) {
                                    if ($e->von && $e->bis && $e->von->lt($hourEnd) && $e->bis->gt($hourStart)) {
                                        $entry = $e;
                                        break;
                                    }
                                }

                                if ($hasOncall)        $cClass = $bg ? 'rob'  : 'rogb';
                                elseif ($hasNotdienst) $cClass = $bg ? 'ron'  : 'rogn';
                                else                   $cClass = $bg ? 'ro'   : 'rog';

                                if ($entry) {
                                    $sClass = match ($entry->gelesen) {
                                        -1      => 'ok',
                                        1       => 'work',
                                        2       => 'off',
                                        3       => 'neu',
                                        default => $hasOncall ? ($bg ? 'lob' : 'logb')
                                                    : ($hasNotdienst ? ($bg ? 'lon' : 'logn')
                                                        : ($bg ? 'lo' : 'log')),
                                    };
                                } else {
                                    if ($hasOncall)        $sClass = $bg ? 'lob' : 'logb';
                                    elseif ($hasNotdienst) $sClass = $bg ? 'lon' : 'logn';
                                    else                   $sClass = $bg ? 'lo'  : 'log';
                                }
                            @endphp
                            <td class="{{ $cClass }}">@if ($entry)<a href="{{ route('legacy.diary.show', [$entry, 'week_date' => ($selectedWeek ?? $monday->format('o-\\WW'))]) }}" class="font-normal hover:underline" title="{{ e($entry->inhalt ?? '') }}">{{ truncate($entry->inhalt ?? '', 10, '') }}</a>@else&nbsp;@endif</td>
                            <td class="{{ $sClass }}">&nbsp;</td>
                        @endforeach

                        <td class="{{ $bg ? 'grau' : 'mitte' }}">{{ $hourLabel }}</td>
                    </tr>
                @endforeach

            @endforeach
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    var scroll  = document.getElementById('week-scroll');
    var table   = document.getElementById('week-table');
    var btn     = document.getElementById('week-fit-toggle');
    var label   = document.getElementById('week-fit-label');
    var minW    = table ? table.dataset.minWidth : '';
    var KEY     = 'workDiaryWeekFit';
    var fitMode = localStorage.getItem(KEY) === '1';

    function apply(fit) {
        if (!scroll || !table) return;
        if (fit) {
            // Fit: table-layout:auto, Tabelle passt sich dem Container an
            table.classList.add('week-table--fit');
            table.style.width = '';
            btn.classList.add('btn-primary');
            btn.classList.remove('btn-ghost');
            label.textContent = '{{ __('Freies Scrollen') }}';
        } else {
            // Scroll: table-layout:fixed, feste Spaltenbreiten, horizontaler Scroll
            table.classList.remove('week-table--fit');
            table.style.width = minW;
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-ghost');
            label.textContent = '{{ __('Auf Bildschirm') }}';
        }
    }

    apply(fitMode);

    if (btn) {
        btn.addEventListener('click', function () {
            fitMode = !fitMode;
            localStorage.setItem(KEY, fitMode ? '1' : '0');
            apply(fitMode);
        });
    }
})();
</script>
@endsection
