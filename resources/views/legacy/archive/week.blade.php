@extends('layouts.app')
@section('title', __('Archiv') . ' — ' . __('Wochenansicht') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Wochenansicht') . ' (' . __('Archiv') . ')')

@section('content')
@php
    /* @var \Carbon\Carbon $monday */
    /* @var \Carbon\Carbon $sunday */
    $weekStart = $monday ?? now()->startOfWeek(\App\Support\WeekDay::MONDAY);
    $dayAbbr = array_map(fn (\CommonToolkit\Enums\Weekday $d) => $d->getShortName('de'), [\CommonToolkit\Enums\Weekday::MONDAY, \CommonToolkit\Enums\Weekday::TUESDAY, \CommonToolkit\Enums\Weekday::WEDNESDAY, \CommonToolkit\Enums\Weekday::THURSDAY, \CommonToolkit\Enums\Weekday::FRIDAY, \CommonToolkit\Enums\Weekday::SATURDAY, \CommonToolkit\Enums\Weekday::SUNDAY]);
    $days    = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));
    $hours   = range(7, 20);
    $legacyUsers = collect($users ?? []);
@endphp

{{-- Füllt den Viewport, fällt aber nie unter --wd-content-min-h (zentral in
     app.css); bei kleinem Viewport scrollt die Seite. Mechanik wie alle
     Legacy-Listen. --}}
<div class="wd-fill-h flex flex-col gap-4">

{{-- Toolbar --}}
<div class="flex-none flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge tone="neutral" size="md" class="mr-1">{{ __('Archiv') }}</x-status-badge>

        <a href="{{ route('legacy.archive.week', ['week' => $weekOffset - 1]) }}"
           class="btn btn-sm btn-ghost" title="{{ __('1 Woche zurück') }}">«</a>
        <a href="{{ route('legacy.archive.week') }}"
           class="btn btn-sm btn-outline">{{ __('Heute') }}</a>
        <a href="{{ route('legacy.archive.week', ['week' => $weekOffset + 1]) }}"
           class="btn btn-sm btn-ghost" title="{{ __('1 Woche vor') }}">»</a>

        <form method="GET" action="{{ route('legacy.archive.week') }}" class="flex items-center gap-2 ml-2">
            <input type="week" name="week_date" value="{{ $selectedWeek ?? $monday->format('o-\\WW') }}"
                   class="input input-bordered input-sm" onchange="this.form.submit()">
        </form>

        <span class="ml-3 font-['Space_Grotesk'] text-base-content">
            <span class="text-sm text-base-content/70">
                {{ $monday->fdate() }} &ndash; {{ $sunday->fdate() }}
            </span>
        </span>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        {{-- Cross-Links --}}
        <a href="{{ route('legacy.archive.index') }}" class="btn btn-sm btn-ghost">{{ __('Archivliste') }}</a>
        <a href="{{ route('legacy.diary.week', ['week_date' => $selectedWeek ?? $monday->format('o-\\WW')]) }}" class="btn btn-sm btn-ghost">{{ __('Aktive Wochenansicht') }}</a>

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
        <label class="flex cursor-pointer items-center gap-2 text-sm" title="{{ __('Ansicht an Bildschirm anpassen') }}">
            <x-icon name="fit_screen" class="text-base-content/70" />
            <span class="text-sm text-base-content/70">{{ __('Auf Bildschirm') }}</span>
            <input type="checkbox" id="week-fit-toggle" class="toggle toggle-sm toggle-primary">
        </label>
    </div>
</div>

{{-- Card mit Scroll-Container --}}
<div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
    <div id="week-scroll" class="h-full overflow-auto">
        @php
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
                    $holidayName = isset($holidays) ? $holidays->nameFor($day) : null;
                    $isHoliday = $holidayName !== null;
                    if ($isToday)        $dayTh = 'kheute';
                    elseif ($isHoliday)  $dayTh = 'kfeiertag';
                    elseif ($isSunday)   $dayTh = 'kso';
                    elseif ($isSaturday) $dayTh = 'ksa';
                    else                 $dayTh = 'kopf';
                @endphp

                <tr>
                    <th class="{{ $dayTh }} sticky top-0 left-0 z-20" @if ($isHoliday) title="{{ $holidayName }}" @endif>
                        <div>{{ $day->format('d.m.y') }}</div>
                            <div class="holiday-name text-[0.65rem] font-medium leading-tight">{{ $isHoliday ? \CommonToolkit\Helper\Data\StringHelper::truncate($holidayName, 16, '') : strtoupper((string) $dayAbbr[$dayIndex]) }}</div>
                    </th>
                    @foreach ($legacyUsers as $legacyUser)
                        @php
                            $uid          = (int) $legacyUser->id;
                            $hasOncall    = isset($oncallByUserDay[$uid][$dateKey]);
                            $hasNotdienst = ! $hasOncall && isset($notdienstByUserDay[$uid][$dateKey]);
                            if ($hasOncall)        $userTh = 'mitb';
                            elseif ($hasNotdienst) $userTh = 'mitn';
                            else                   $userTh = 'mit';
                        @endphp
                        <th class="{{ $userTh }} sticky top-0 z-10" title="{{ $legacyUser->uname }}">{{ $legacyUser->uname }}</th>
                        <th class="{{ $userTh }} status-col sticky top-0 z-10" aria-hidden="true">&nbsp;</th>
                    @endforeach
                    <th class="{{ $dayTh }} sticky top-0 z-10" @if ($isHoliday) title="{{ $holidayName }}" @endif>
                        <div>{{ $dayAbbr[$dayIndex] }}</div>
                            <div class="holiday-name text-[0.65rem] font-medium leading-tight">{{ $isHoliday ? __('Feiertag') : ' ' }}</div>
                    </th>
                </tr>

                @foreach ($hours as $hourIndex => $hour)
                    @php
                        $bg        = $hourIndex % 2 === 0;
                        $hourStart = $day->copy()->setTime($hour, 0, 0);
                        $hourEnd   = $day->copy()->setTime($hour + 1, 0, 0);
                        $hourLabel = sprintf('%02d–%02d', $hour, $hour + 1);
                    @endphp
                    <tr>
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
                            <td class="{{ $cClass }}">@if ($entry)<a href="{{ route('legacy.archive.show', [$entry, 'week_date' => ($selectedWeek ?? $monday->format('o-\\WW'))]) }}" data-entry-modal-trigger class="font-normal hover:underline" title="{{ e($entry->inhalt ?? '') }}">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->inhalt ?? '', 10, '') }}</a>@else&nbsp;@endif</td>
                            <td class="{{ $sClass }} status-col">&nbsp;</td>
                        @endforeach

                        <td class="{{ $bg ? 'grau' : 'mitte' }}">{{ $hourLabel }}</td>
                    </tr>
                @endforeach

            @endforeach
            </tbody>
        </table>
    </div>
</div>
</div>
<script>
(function () {
    var scroll  = document.getElementById('week-scroll');
    var table   = document.getElementById('week-table');
    var btn     = document.getElementById('week-fit-toggle');
    var minW    = table ? table.dataset.minWidth : '';
    var KEY     = 'workDiaryWeekFit';
    // Default: Fit-to-Screen aktiv. Nutzerwahl per localStorage hat Vorrang.
    var stored  = localStorage.getItem(KEY);
    var fitMode = stored === null ? true : stored === '1';

    function apply(fit) {
        if (!scroll || !table) return;
        if (fit) {
            table.classList.add('week-table--fit');
            table.style.width = '';
            btn.checked = true;
        } else {
            table.classList.remove('week-table--fit');
            table.style.width = minW;
            btn.checked = false;
        }
    }

    apply(fitMode);

    if (btn) {
        btn.addEventListener('change', function () {
            fitMode = btn.checked;
            localStorage.setItem(KEY, fitMode ? '1' : '0');
            apply(fitMode);
        });
    }
})();
</script>
@endsection
