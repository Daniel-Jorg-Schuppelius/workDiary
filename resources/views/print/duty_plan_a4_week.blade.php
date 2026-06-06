@extends('layouts.print')

@section('content')
@php
    /** @var \App\Models\DutyPlan $dutyPlan */
    /** @var \Carbon\CarbonImmutable $from */
    /** @var \Carbon\CarbonImmutable $to */
    /** @var list<string> $dates */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var array<int, array<string, list<\App\Models\ScheduledShift>>> $matrix */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ShiftType> $shiftTypes */
    /** @var \App\Services\HolidayService $holidays */
    /** @var bool $anonymous */
@endphp

@include('print._header', [
    'title'       => $title,
    'subtitle'    => $dutyPlan->title . ($anonymous ? ' · ' . __('Anonymisiert') : ''),
    'org'         => $org ?? null,
    'extraMeta'   => $from->fdate() . ' – ' . $to->fdate(),
])

@if ($users->isEmpty())
    <p class="muted">{{ __('Keine Schichten in dieser Woche.') }}</p>
@else
    <table>
        <colgroup>
            <col style="width: 40mm;">
            @foreach ($dates as $d)
                <col>
            @endforeach
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                @foreach ($dates as $d)
                    @php
                        $carbon = \Carbon\CarbonImmutable::parse($d);
                        $hName  = $holidays->nameFor($carbon);
                        $cls    = $carbon->isWeekend() ? 'weekend center' : 'center';
                        $cls    = $hName ? 'holiday center' : $cls;
                        if ($carbon->isSunday()) { $cls .= ' sunday'; }
                    @endphp
                    <th class="{{ $cls }}" title="{{ $hName ?? '' }}">
                        <div class="small">{{ $carbon->translatedFormat('l') }}</div>
                        <div>{{ $carbon->format('d.m.') }}</div>
                        @if ($hName)
                            <div class="small muted">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($hName, 18) }}</div>
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $u)
                <tr>
                    <td><strong>{{ $anonymous ? \CommonToolkit\Helper\Data\StringHelper::printableInitials($u->name) : $u->name }}</strong></td>
                    @foreach ($dates as $d)
                        @php
                            $carbon = \Carbon\CarbonImmutable::parse($d);
                            $hName  = $holidays->nameFor($carbon);
                            $cls    = $carbon->isWeekend() ? 'weekend' : '';
                            $cls    = $hName ? 'holiday' : $cls;
                            if ($carbon->isSunday()) { $cls .= ' sunday'; }
                            $cellShifts = $matrix[$u->id][$d] ?? [];
                        @endphp
                        <td class="{{ $cls }}">
                            @foreach ($cellShifts as $shift)
                                @php
                                    $color = $shift->shiftType?->color ?? '#6b7280';
                                    $abbr  = $shift->shiftType?->abbreviation ?? '?';
                                    $start = $shift->resolvedStartTime();
                                    $end   = $shift->resolvedEndTime();
                                @endphp
                                <div style="margin-bottom:2px;">
                                    <span class="badge" style="background:{{ $color }};">{{ $abbr }}</span>
                                    @if ($start)<span class="small">{{ $start }}@if ($end)–{{ $end }}@endif</span>@endif
                                </div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($shiftTypes->isNotEmpty())
        <div class="legend">
            <strong>{{ __('Legende') }}:</strong>
            @foreach ($shiftTypes as $st)
                <span><span class="badge" style="background:{{ $st->color }};">{{ $st->abbreviation }}</span> {{ $st->name }}</span>
            @endforeach
        </div>
    @endif
@endif

<div class="footer">
    <span>{{ $org ?? '' }} · {{ $dutyPlan->title }} · KW {{ $from->weekOfYear }} / {{ $from->year }}</span>
    <span>{{ now()->fdatetime() }}</span>
</div>
@endsection
