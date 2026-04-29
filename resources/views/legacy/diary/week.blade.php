@extends('layouts.app')
@section('title', 'Wochenansicht — ' . config('app.name', 'WorkDiary'))
@section('nav-title', 'Wochenansicht')

@section('content')
@php
    /* @var \Carbon\Carbon $monday */
    /* @var \Carbon\Carbon $sunday */
    $weekStart = $monday ?? now()->startOfWeek(\Carbon\Carbon::MONDAY);
    $dayAbbr = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $days    = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));
    $hours   = range(7, 20); // Slots 07–08 bis 20–21
@endphp

<div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
    <section class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-sm md:p-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-base-content/60">Kalenderwoche</p>
                <p class="mt-1 font-['Space_Grotesk'] text-lg font-semibold text-base-content">{{ $monday->format('d.m.Y') }} &ndash; {{ $sunday->format('d.m.Y') }}</p>
            </div>
            <form method="GET" action="{{ route('legacy.diary.week') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-base-content">Woche wählen</label>
                    <input type="week" name="week_date" value="{{ $selectedWeek ?? $monday->format('o-\\WW') }}" class="input input-bordered input-sm">
                </div>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Woche anzeigen') }}</button>
                <span class="mx-1 h-8 w-px self-end bg-base-300" aria-hidden="true"></span>
                <a href="{{ route('legacy.diary.week', ['week' => $weekOffset - 1]) }}" class="btn btn-sm btn-neutral" title="1 Woche zurück">&#9664; Vorwoche</a>
                <a href="{{ route('legacy.diary.week', ['week' => $weekOffset + 1]) }}" class="btn btn-sm btn-neutral" title="{{ __('1 Woche vor') }}">{{ __('Folgewoche') }} &#9654;</a>
                @if ($weekOffset !== 0)
                    <a href="{{ route('legacy.diary.week') }}" class="btn btn-sm btn-outline">{{ __('Aktuelle Woche') }}</a>
                @endif
            </form>
        </div>
    </section>

    <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-sm">
        <div class="h-full overflow-auto rounded-box p-2">
        <table class="week-table">
        <tbody>
        @foreach ($days as $dayIndex => $day)
            @php
                $dateKey   = $day->format('Y-m-d');
                $isToday   = $day->isToday();
                $isSunday  = (int) $day->dayOfWeek === 0;
                $isSaturday = (int) $day->dayOfWeek === 6;
                if ($isToday)       $dayTh = 'kheute';
                elseif ($isSunday)  $dayTh = 'kso';
                elseif ($isSaturday) $dayTh = 'ksa';
                else                $dayTh = 'kopf';
            @endphp

            {{-- Tages-Kopfzeile --}}
            <tr>
                <th class="{{ $dayTh }}">{{ $day->format('d.m.y') }}</th>
                @foreach ($users as $user)
                    @php
                        $uid         = (int) $user->id;
                        $hasOncall   = isset($oncallByUserDay[$uid][$dateKey]);
                        $hasNotdienst = ! $hasOncall && isset($notdienstByUserDay[$uid][$dateKey]);
                        if ($hasOncall)       $userTh = 'mitb';
                        elseif ($hasNotdienst) $userTh = 'mitn';
                        else                  $userTh = 'mit';
                    @endphp
                    <th colspan="2" class="{{ $userTh }}">{{ $user->uname }}</th>
                @endforeach
                <th class="{{ $dayTh }}">{{ $dayAbbr[$dayIndex] }}</th>
            </tr>

            {{-- Stundenzeilen 07–21 --}}
            @foreach ($hours as $hourIndex => $hour)
                @php
                    $bg        = $hourIndex % 2 === 0;
                    $hourStart = $day->copy()->setTime($hour, 0, 0);
                    $hourEnd   = $day->copy()->setTime($hour + 1, 0, 0);
                    $hourLabel = sprintf('%02d - %02d', $hour, $hour + 1);
                @endphp
                <tr>
                    <td class="{{ $bg ? 'grau' : 'mitte' }}">{{ $hourLabel }}</td>

                    @foreach ($users as $user)
                        @php
                            $uid          = (int) $user->id;
                            $hasOncall    = isset($oncallByUserDay[$uid][$dateKey]);
                            $hasNotdienst = ! $hasOncall && isset($notdienstByUserDay[$uid][$dateKey]);

                            // Passenden Eintrag finden (erster der das Stunden-Fenster überlappt)
                            $entry = null;
                            foreach (($entriesByUserDay[$uid][$dateKey] ?? []) as $e) {
                                if ($e->von && $e->bis && $e->von->lt($hourEnd) && $e->bis->gt($hourStart)) {
                                    $entry = $e;
                                    break;
                                }
                            }

                            // Inhaltszelle (ro/rob/ron/rog/rogb/rogn)
                            if ($hasOncall)       $cClass = $bg ? 'rob'  : 'rogb';
                            elseif ($hasNotdienst) $cClass = $bg ? 'ron'  : 'rogn';
                            else                  $cClass = $bg ? 'ro'   : 'rog';

                            // Statuszelle (ok/work/off/neu oder lo-Variante)
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
                                if ($hasOncall)       $sClass = $bg ? 'lob' : 'logb';
                                elseif ($hasNotdienst) $sClass = $bg ? 'lon' : 'logn';
                                else                  $sClass = $bg ? 'lo'  : 'log';
                            }
                        @endphp
                        <td class="{{ $cClass }}">@if ($entry)<a href="{{ route('legacy.diary.show', [$entry, 'week_date' => ($selectedWeek ?? $monday->format('o-\\WW'))]) }}" class="font-normal hover:underline" title="{{ e($entry->inhalt ?? '') }}">{{ \Illuminate\Support\Str::limit($entry->inhalt ?? '', 10, '') }}</a>@else&nbsp;@endif</td>
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

{{-- Legende --}}
    <div class="flex-none rounded-box border border-base-300 bg-base-100 p-3 shadow-sm">
        <table class="week-table w-auto!">
            <tr>
                <td class="rogb">&nbsp;&nbsp;</td>
                <td class="lob">&nbsp;&nbsp;</td>
                <td class="px-2 text-xs">&nbsp; allgemeine Bereitschaft</td>
                <td class="rogn">&nbsp;&nbsp;</td>
                <td class="lon">&nbsp;&nbsp;</td>
                <td class="px-2 text-xs">&nbsp; Notdienst-Bereitschaft</td>
            </tr>
        </table>
    </div>
</div>
@endsection
