@extends('layouts.print')

@section('content')
@php
    /** @var int $year */
    /** @var \Carbon\CarbonImmutable $from */
    /** @var \Carbon\CarbonImmutable $to */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Vacation> $vacations */
    /** @var \App\Services\HolidayService $holidays */
    /** @var bool $anonymous */

    // Group vacations per user_id => Collection
    $byUser = $vacations->groupBy('user_id');

    // Build per-user per-day status: 'A' approved, 'P' pending, '' nothing
    $statusFor = static function (int $userId, string $date) use ($byUser): string {
        $userVacs = $byUser->get($userId);
        if (! $userVacs) return '';
        foreach ($userVacs as $v) {
            if ($date >= $v->start_date->toDateString() && $date <= $v->end_date->toDateString()) {
                return $v->status === \App\Models\Vacation::STATUS_APPROVED ? 'A' : 'P';
            }
        }
        return '';
    };
@endphp

@include('print._header', [
    'title'     => $title,
    'subtitle'  => ($anonymous ? __('pdf.vacation_year.anonymized') . ' · ' : '') . $users->count() . ' ' . __('pdf.vacation_year.employees'),
    'org'       => $org ?? null,
])

@if ($users->isEmpty())
    <p class="muted">{{ __('pdf.vacation_year.no_data', ['year' => $year]) }}</p>
@else
    @foreach (range(1, 12) as $monthNum)
        @php
            $monthStart = \Carbon\CarbonImmutable::create($year, $monthNum, 1);
            $monthEnd   = $monthStart->endOfMonth();
            $daysInMonth = $monthEnd->day;
        @endphp
        <h2>{{ $monthStart->translatedFormat('F') }}</h2>
        <table>
            <colgroup>
                <col style="width: 32mm;">
                @for ($d = 1; $d <= $daysInMonth; $d++)
                    <col>
                @endfor
            </colgroup>
            <thead>
                <tr>
                    <th>{{ __('pdf.vacation_year.employees') }}</th>
                    @for ($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $cur    = $monthStart->day($d);
                            $hName  = $holidays->nameFor($cur);
                            $cls    = $cur->isWeekend() ? 'weekend center small' : 'center small';
                            $cls    = $hName ? 'holiday center small' : $cls;
                            if ($cur->isSunday()) { $cls .= ' sunday'; }
                        @endphp
                        <th class="{{ $cls }}" title="{{ $hName ?? '' }}">{{ $d }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td>{{ $anonymous ? printable_initials($u->name) : truncate($u->name, 22) }}</td>
                        @for ($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $cur    = $monthStart->day($d);
                                $dateS  = $cur->toDateString();
                                $hName  = $holidays->nameFor($cur);
                                $cls    = $cur->isWeekend() ? 'weekend center' : 'center';
                                $cls    = $hName ? 'holiday center' : $cls;
                                if ($cur->isSunday()) { $cls .= ' sunday'; }
                                $status = $statusFor($u->id, $dateS);
                                $bg     = $status === 'A' ? '#10b981' : ($status === 'P' ? '#f59e0b' : null);
                            @endphp
                            <td class="{{ $cls }}" @if ($bg) style="background:{{ $bg }};color:#fff;font-weight:600;" @endif>
                                {{ $status }}
                            </td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="legend">
        <strong>{{ __('pdf.vacation_year.legend') }}:</strong>
        <span><span class="badge" style="background:#10b981;">A</span> {{ __('pdf.vacation_year.approved') }}</span>
        <span><span class="badge" style="background:#f59e0b;">P</span> {{ __('pdf.vacation_year.pending') }}</span>
        <span><span class="badge" style="background:#fff5d6;color:#111;">{{ __('pdf.vacation_year.holiday') }}</span></span>
        <span><span class="badge" style="background:#f4f4f4;color:#111;">{{ __('pdf.vacation_year.weekend') }}</span></span>
    </div>
@endif

<div class="footer">
    <span>{{ $org ?? '' }} · {{ __('Urlaubsübersicht') }} {{ $year }}</span>
    <span>{{ now()->format('d.m.Y H:i') }}</span>
</div>
@endsection
