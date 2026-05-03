@extends('layouts.app')
@section('title', __('Legacy Archiv Woche') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Legacy') . ' / ' . __('Archivwoche'))

@section('content')
@php
    $weekStart = $monday ?? now()->startOfWeek(\Carbon\Carbon::MONDAY);
    $dayAbbr = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $days = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));
    $hours = range(7, 20);
    $legacyUsers = collect($users ?? []);
@endphp

<div class="mb-3 flex flex-wrap items-center gap-2">
    <a href="{{ route('legacy.archive.index') }}" class="btn btn-xs btn-ghost">{{ __('Archivliste') }}</a>
    <a href="{{ route('legacy.archive.week', ['week' => $weekOffset - 1]) }}" class="btn btn-xs btn-ghost" title="{{ __('1 Woche zurück') }}">&#9664; {{ __('Vorwoche') }}</a>
    <span class="text-sm font-semibold">{{ $monday->format('d.m.Y') }} &ndash; {{ $sunday->format('d.m.Y') }}</span>
    <a href="{{ route('legacy.archive.week', ['week' => $weekOffset + 1]) }}" class="btn btn-xs btn-ghost" title="{{ __('1 Woche vor') }}">{{ __('Folgewoche') }} &#9654;</a>
    <form method="GET" action="{{ route('legacy.archive.week') }}" class="flex items-center gap-2">
        <input type="week" name="week_date" value="{{ $selectedWeek ?? $monday->format('o-\\WW') }}" class="input input-bordered input-xs">
        <button type="submit" class="btn btn-xs btn-primary">{{ __('Woche anzeigen') }}</button>
    </form>
    @if ($weekOffset !== 0)
        <a href="{{ route('legacy.archive.week') }}" class="btn btn-xs btn-outline">{{ __('Aktuelle Woche') }}</a>
    @endif
</div>

<div class="overflow-x-auto">
    <table class="week-table">
        <tbody>
        @foreach ($days as $dayIndex => $day)
            @php
                $dateKey = $day->format('Y-m-d');
                $isToday = $day->isToday();
                $isSunday = (int) $day->dayOfWeek === 0;
                $isSaturday = (int) $day->dayOfWeek === 6;
                if ($isToday) $dayTh = 'kheute';
                elseif ($isSunday) $dayTh = 'kso';
                elseif ($isSaturday) $dayTh = 'ksa';
                else $dayTh = 'kopf';
            @endphp

            <tr>
                <th class="{{ $dayTh }}">{{ $day->format('d.m.y') }}</th>
                @foreach ($legacyUsers as $legacyUser)
                    @php
                        $uid = (int) $legacyUser->id;
                        $hasOncall = isset($oncallByUserDay[$uid][$dateKey]);
                        $hasNotdienst = ! $hasOncall && isset($notdienstByUserDay[$uid][$dateKey]);
                        if ($hasOncall) $userTh = 'mitb';
                        elseif ($hasNotdienst) $userTh = 'mitn';
                        else $userTh = 'mit';
                    @endphp
                    <th colspan="2" class="{{ $userTh }}">{{ $legacyUser->uname }}</th>
                @endforeach
                <th class="{{ $dayTh }}">{{ $dayAbbr[$dayIndex] }}</th>
            </tr>

            @foreach ($hours as $hourIndex => $hour)
                @php
                    $bg = $hourIndex % 2 === 0;
                    $hourStart = $day->copy()->setTime($hour, 0, 0);
                    $hourEnd = $day->copy()->setTime($hour + 1, 0, 0);
                    $hourLabel = sprintf('%02d - %02d', $hour, $hour + 1);
                @endphp
                <tr>
                    <td class="{{ $bg ? 'grau' : 'mitte' }}">{{ $hourLabel }}</td>

                    @foreach ($legacyUsers as $legacyUser)
                        @php
                            $uid = (int) $legacyUser->id;
                            $hasOncall = isset($oncallByUserDay[$uid][$dateKey]);
                            $hasNotdienst = ! $hasOncall && isset($notdienstByUserDay[$uid][$dateKey]);

                            $entry = null;
                            foreach (($entriesByUserDay[$uid][$dateKey] ?? []) as $e) {
                                if ($e->von && $e->bis && $e->von->lt($hourEnd) && $e->bis->gt($hourStart)) {
                                    $entry = $e;
                                    break;
                                }
                            }

                            if ($hasOncall) $cClass = $bg ? 'rob' : 'rogb';
                            elseif ($hasNotdienst) $cClass = $bg ? 'ron' : 'rogn';
                            else $cClass = $bg ? 'ro' : 'rog';

                            if ($entry) {
                                $sClass = match ($entry->gelesen) {
                                    -1 => 'ok',
                                    1 => 'work',
                                    2 => 'off',
                                    3 => 'neu',
                                    default => $hasOncall ? ($bg ? 'lob' : 'logb')
                                        : ($hasNotdienst ? ($bg ? 'lon' : 'logn')
                                            : ($bg ? 'lo' : 'log')),
                                };
                            } else {
                                if ($hasOncall) $sClass = $bg ? 'lob' : 'logb';
                                elseif ($hasNotdienst) $sClass = $bg ? 'lon' : 'logn';
                                else $sClass = $bg ? 'lo' : 'log';
                            }
                        @endphp
                        <td class="{{ $cClass }}">@if ($entry)<span title="{{ e($entry->inhalt ?? '') }}">{{ \Illuminate\Support\Str::limit($entry->inhalt ?? '', 10, '') }}</span>@else&nbsp;@endif</td>
                        <td class="{{ $sClass }}">&nbsp;</td>
                    @endforeach

                    <td class="{{ $bg ? 'grau' : 'mitte' }}">{{ $hourLabel }}</td>
                </tr>
            @endforeach

        @endforeach
        </tbody>
    </table>
</div>
@endsection
