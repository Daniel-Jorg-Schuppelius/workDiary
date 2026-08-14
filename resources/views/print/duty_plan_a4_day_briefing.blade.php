{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duty_plan_a4_day_briefing.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.print')

@section('content')
@php
    /** @var \App\Models\DutyPlan $dutyPlan */
    /** @var \Carbon\CarbonImmutable $date */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ScheduledShift> $shifts */
    /** @var ?string $holidayName */
    /** @var bool $anonymous */

    $subtitle = $dutyPlan->title;
    if ($holidayName) {
        $subtitle .= ' · ' . __('Feiertag') . ': ' . $holidayName;
    }
    if ($anonymous) {
        $subtitle .= ' · ' . __('Anonymisiert');
    }

    // Helper for timeline placement (24h grid)
    $toMinutes = static function (?string $hms): ?int {
        if (! $hms) {
            return null;
        }
        $parts = explode(':', $hms);
        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    };
@endphp

@include('print._header', [
    'title'       => $title,
    'subtitle'    => $subtitle,
    'org'         => $org ?? null,
    'extraMeta'   => __('Schichten') . ': ' . $shifts->count(),
])

@if ($shifts->isEmpty())
    <p class="muted">{{ __('Keine Schichten an diesem Tag.') }}</p>
@else
    <h2>{{ __('Übersicht') }}</h2>
    <table>
        <colgroup>
            <col style="width: 36mm;">
            <col style="width: 38mm;">
            <col style="width: 22mm;">
            <col style="width: 22mm;">
            <col>
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                <th>{{ __('Schichttyp') }}</th>
                <th>{{ __('Beginn') }}</th>
                <th>{{ __('Ende') }}</th>
                <th>{{ __('Notiz') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($shifts as $shift)
                <tr>
                    <td><strong>{{ $anonymous ? \CommonToolkit\Helper\Data\StringHelper::printableInitials($shift->user?->name) : ($shift->user?->name ?? '—') }}</strong></td>
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
        </tbody>
    </table>

    <h2>{{ __('Zeitstrahl') }} (00–24)</h2>
    <div>
        @foreach ($shifts as $shift)
            @php
                $start = $toMinutes($shift->resolvedStartTime());
                $end   = $toMinutes($shift->resolvedEndTime());
                $color = $shift->shiftType?->color ?? '#6b7280';
                $abbr  = $shift->shiftType?->abbreviation ?? '?';
                $name  = $anonymous ? \CommonToolkit\Helper\Data\StringHelper::printableInitials($shift->user?->name) : ($shift->user?->name ?? '—');
                $hasTimes = $start !== null && $end !== null && $end > $start;
                if ($hasTimes) {
                    $left  = ($start / 1440) * 100;
                    $width = max(2, (($end - $start) / 1440) * 100);
                }
            @endphp
            <div class="timeline-row">
                <div class="timeline-name">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($name, 18) }}</div>
                <div class="timeline-bar">
                    @if ($hasTimes)
                        <div class="block" style="left: {{ $left }}%; width: {{ $width }}%; background: {{ $color }};">
                            {{ $abbr }} {{ $shift->resolvedStartTime() }}–{{ $shift->resolvedEndTime() }}
                        </div>
                    @else
                        <div class="block" style="left: 0; width: 100%; background: {{ $color }}; opacity: 0.5;">
                            {{ $abbr }} {{ __('ganztägig') }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
        <div class="timeline-axis">
            @for ($h = 0; $h <= 24; $h += 3)
                <span>{{ str_pad((string) $h, 2, '0', STR_PAD_LEFT) }}</span>
            @endfor
        </div>
    </div>
@endif

<div class="footer">
    <span>{{ $org ?? '' }} · {{ $dutyPlan->title }}</span>
    <span>{{ now()->fdatetime() }}</span>
</div>
@endsection
