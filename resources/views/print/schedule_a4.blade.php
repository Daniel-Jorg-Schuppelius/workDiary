@extends('layouts.print')

@section('content')
@php
    /** @var \Carbon\CarbonImmutable $from */
    /** @var \Carbon\CarbonImmutable $to */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Models\ScheduledShift>> $byUserDate */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ShiftType> $shiftTypes */
    /** @var \App\Services\HolidayService $holidays */
    /** @var bool $anonymous */

    $days = [];
    for ($d = $from->startOfDay(); $d->lte($to); $d = $d->addDay()) {
        $days[] = $d;
    }

    // Schicht-Kürzel: Abkürzung, sonst die ersten drei Buchstaben des Namens.
    $abbr = static function (?\App\Models\ShiftType $t): string {
        if (! $t) {
            return '—';
        }
        $a = trim((string) $t->abbreviation);
        return $a !== '' ? $a : \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($t->name, 0, 3));
    };
@endphp

@include('print._header', [
    'title'    => $title,
    'subtitle' => $subtitle . ' · ' . $users->count() . ' ' . __('Mitarbeiter'),
    'org'      => $org ?? null,
])

@if ($users->isEmpty())
    <p class="muted">{{ __('Keine Schichten im gewählten Zeitraum.') }}</p>
@else
    <table>
        <colgroup>
            <col style="width: 32mm;">
            @foreach ($days as $cur)<col>@endforeach
        </colgroup>
        <thead>
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                @foreach ($days as $cur)
                    @php
                        $hName = $holidays->nameFor($cur);
                        $cls = $cur->isWeekend() ? 'weekend center small' : 'center small';
                        $cls = $hName ? 'holiday center small' : $cls;
                        if ($cur->isSunday()) { $cls .= ' sunday'; }
                    @endphp
                    <th class="{{ $cls }}" title="{{ $hName ?? '' }}">
                        {{ $cur->day }}<br><span class="small muted">{{ $cur->translatedFormat('D') }}</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $u)
                <tr>
                    <td>{{ $anonymous ? \CommonToolkit\Helper\Data\StringHelper::printableInitials($u->name) : \CommonToolkit\Helper\Data\StringHelper::truncate($u->name, 22) }}</td>
                    @foreach ($days as $cur)
                        @php
                            $hName = $holidays->nameFor($cur);
                            $cls = $cur->isWeekend() ? 'weekend center' : 'center';
                            $cls = $hName ? 'holiday center' : $cls;
                            if ($cur->isSunday()) { $cls .= ' sunday'; }
                            $cellShifts = $byUserDate->get($u->id . '|' . $cur->toDateString()) ?? collect();
                        @endphp
                        <td class="{{ $cls }}">
                            @foreach ($cellShifts as $s)
                                <span class="badge" style="background:{{ $s->shiftType?->color ?? '#6b7280' }};">{{ $abbr($s->shiftType) }}</span>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="legend">
        @foreach ($shiftTypes as $t)
            <span><span class="badge" style="background:{{ $t->color ?? '#6b7280' }};">{{ $abbr($t) }}</span> {{ $t->name }}</span>
        @endforeach
        <span><span class="badge" style="background:#fff5d6;color:#111;">{{ __('Feiertag') }}</span></span>
        <span><span class="badge" style="background:#f4f4f4;color:#111;">{{ __('Wochenende') }}</span></span>
    </div>
@endif

<div class="footer">
    <span>{{ $org ?? '' }} · {{ __('Schichtplan') }} {{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }}</span>
    <span>{{ now()->fdatetime() }}</span>
</div>
@endsection
