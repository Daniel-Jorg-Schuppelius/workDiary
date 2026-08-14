{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duty_plan_a3_roster.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.print')

@section('content')
@php
    /** @var \App\Models\DutyPlan $dutyPlan */
    /** @var list<string> $dates */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var array<int, array<string, list<\App\Models\ScheduledShift>>> $matrix */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ShiftType> $shiftTypes */
    /** @var \App\Services\HolidayService $holidays */
    /** @var bool $anonymous */

    $extraMeta = sprintf(
        '%s %s – %s · %d %s',
        __('Zeitraum'),
        $dutyPlan->from_date->fdate(),
        $dutyPlan->to_date->fdate(),
        $users->count(),
        __('Mitarbeiter'),
    );
@endphp

@include('print._header', [
    'title'       => $title,
    'subtitle'    => $anonymous ? __('Anonymisierte Variante') : null,
    'org'         => $org ?? null,
    'extraMeta'   => $extraMeta,
])

@if ($users->isEmpty() || count($dates) === 0)
    <p class="muted">{{ __('Keine Schichten in diesem Plan.') }}</p>
@else
    @php
        // Adaptive column width: A3 landscape inner ≈ 410mm; reserve 30mm for name column.
        $colWidth = max(7, (int) floor(380 / max(1, count($dates))));
    @endphp
    <table>
        <colgroup>
            <col style="width: 30mm;">
            @foreach ($dates as $d)
                <col style="width: {{ $colWidth }}mm;">
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
                        <div class="small">{{ $carbon->translatedFormat('D') }}</div>
                        <div>{{ $carbon->format('d.m.') }}</div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $u)
                <tr>
                    <td>
                        <strong>{{ $anonymous ? \CommonToolkit\Helper\Data\StringHelper::printableInitials($u->name) : $u->name }}</strong>
                    </td>
                    @foreach ($dates as $d)
                        @php
                            $carbon = \Carbon\CarbonImmutable::parse($d);
                            $hName  = $holidays->nameFor($carbon);
                            $cls    = $carbon->isWeekend() ? 'weekend center' : 'center';
                            $cls    = $hName ? 'holiday center' : $cls;
                            if ($carbon->isSunday()) { $cls .= ' sunday'; }
                            $cellShifts = $matrix[$u->id][$d] ?? [];
                        @endphp
                        <td class="{{ $cls }}">
                            @foreach ($cellShifts as $shift)
                                @php
                                    $color = $shift->shiftType?->color ?? '#6b7280';
                                    $abbr  = $shift->shiftType?->abbreviation ?? '?';
                                @endphp
                                <span class="badge" style="background:{{ $color }};">{{ $abbr }}</span>
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
                <span><span class="badge" style="background:{{ $st->color }};">{{ $st->abbreviation }}</span> {{ $st->name }}@if ($st->default_start_time) ({{ $st->default_start_time }}@if ($st->default_end_time)–{{ $st->default_end_time }}@endif)@endif</span>
            @endforeach
        </div>
    @endif

    {{-- Soll/Ist-Übersicht --}}
    <div style="margin-top: 12px;">
        <strong>{{ __('Soll/Ist-Besetzung') }}:</strong>
        @include('coverage-requirements._heatmap', ['dutyPlan' => $dutyPlan, 'compact' => true, 'forPrint' => true])
    </div>
@endif

<div class="footer">
    <span>{{ $org ?? '' }} · {{ $dutyPlan->title }}</span>
    <span>{{ now()->fdatetime() }}</span>
</div>
@endsection
