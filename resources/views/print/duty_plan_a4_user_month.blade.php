@extends('layouts.print')

@section('content')
@php
    /** @var \App\Models\User $user */
    /** @var \Carbon\CarbonImmutable $month */
    /** @var \Carbon\CarbonImmutable $end */
    /** @var list<string> $dates */
    /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Models\ScheduledShift>> $shifts */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Vacation> $vacations */
    /** @var \App\Services\HolidayService $holidays */
    /** @var bool $anonymous */

    $totalMinutes = 0;
    foreach ($shifts as $dayShifts) {
        foreach ($dayShifts as $s) {
            $start = $s->resolvedStartTime();
            $end_  = $s->resolvedEndTime();
            if ($start && $end_) {
                [$sh, $sm] = array_pad(array_map('intval', explode(':', $start)), 2, 0);
                [$eh, $em] = array_pad(array_map('intval', explode(':', $end_)), 2, 0);
                $diff = ($eh * 60 + $em) - ($sh * 60 + $sm);
                if ($diff > 0) {
                    $totalMinutes += $diff;
                }
            }
        }
    }
    $totalHours = number_format($totalMinutes / 60, 2, ',', '');

    $vacationByDate = [];
    foreach ($vacations as $v) {
        $cursor = $v->start_date->copy();
        while ($cursor->lte($v->end_date)) {
            $vacationByDate[$cursor->toDateString()] = $v;
            $cursor->addDay();
        }
    }
@endphp

@include('print._header', [
    'title'       => $title,
    'subtitle'    => $month->translatedFormat('F Y'),
    'org'         => $org ?? null,
    'extraMeta'   => __('Summe') . ': ' . $totalHours . ' h',
])

<table>
    <colgroup>
        <col style="width: 12mm;">
        <col style="width: 12mm;">
        <col style="width: 32mm;">
        <col style="width: 18mm;">
        <col style="width: 18mm;">
        <col>
    </colgroup>
    <thead>
        <tr>
            <th>{{ __('Tag') }}</th>
            <th>{{ __('Datum') }}</th>
            <th>{{ __('Schicht') }}</th>
            <th>{{ __('Beginn') }}</th>
            <th>{{ __('Ende') }}</th>
            <th>{{ __('Notiz') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($dates as $d)
            @php
                $carbon  = \Carbon\CarbonImmutable::parse($d);
                $hName   = $holidays->nameFor($carbon);
                $cls     = $carbon->isWeekend() ? 'weekend' : '';
                $cls     = $hName ? 'holiday' : $cls;
                if ($carbon->isSunday()) { $cls .= ' sunday'; }
                $vac     = $vacationByDate[$d] ?? null;
                $dayShifts = $shifts->get($d, collect());
                $rowspan = max(1, $dayShifts->count() + ($vac ? 1 : 0));
            @endphp
            @if ($dayShifts->isEmpty() && $vac === null)
                <tr class="{{ $cls }}">
                    <td>{{ $carbon->translatedFormat('D') }}</td>
                    <td>{{ $carbon->format('d.m.') }}</td>
                    <td colspan="4" class="muted">
                        @if ($hName)
                            <em>{{ $hName }}</em>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @else
                @php $first = true; @endphp
                @if ($vac)
                    <tr class="{{ $cls }}">
                        @if ($first)
                            <td rowspan="{{ $rowspan }}">{{ $carbon->translatedFormat('D') }}</td>
                            <td rowspan="{{ $rowspan }}">{{ $carbon->format('d.m.') }}</td>
                            @php $first = false; @endphp
                        @endif
                        <td><em>{{ $vac->typeLabel() }}</em>@if ($hName) · {{ $hName }} @endif</td>
                        <td colspan="3" class="muted">{{ $vac->statusLabel() }}</td>
                    </tr>
                @endif
                @foreach ($dayShifts as $shift)
                    <tr class="{{ $cls }}">
                        @if ($first)
                            <td rowspan="{{ $rowspan }}">{{ $carbon->translatedFormat('D') }}</td>
                            <td rowspan="{{ $rowspan }}">{{ $carbon->format('d.m.') }}</td>
                            @php $first = false; @endphp
                        @endif
                        <td>
                            @if ($shift->shiftType)
                                <span class="badge" style="background:{{ $shift->shiftType->color }};">{{ $shift->shiftType->abbreviation }}</span>
                                {{ $shift->shiftType->name }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $shift->resolvedStartTime() ?? '—' }}</td>
                        <td>{{ $shift->resolvedEndTime() ?? '—' }}</td>
                        <td>{{ $shift->note }}</td>
                    </tr>
                @endforeach
            @endif
        @endforeach
    </tbody>
</table>

<div class="footer">
    <span>{{ $org ?? '' }} · {{ $anonymous ? printable_initials($user->name) : $user->name }}</span>
    <span>{{ now()->format('d.m.Y H:i') }} · {{ __('Summe') }} {{ $totalHours }} h</span>
</div>
@endsection
