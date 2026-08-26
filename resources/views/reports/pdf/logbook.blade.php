{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : logbook.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Fahrtenbuch ' . $vehicle->license_plate . ' – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('Fahrtenbuch') . ' ' . $vehicle->displayName())

@section('pdf-meta')
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ $vehicle->logbook_mode ? 'Fahrtenbuch-Modus (festgeschrieben, lückenlos)' : 'Kein Fahrtenbuch-Modus' }} ·
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @php
        $num = fn (float|int $v, int $d = 0) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, $d, withThousandsSeparator: true);
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">Fahrten</div><div class="value">{{ $totals['trips'] }}</div></td>
            <td><div class="label">Σ km</div><div class="value">{{ $num($totals['km']) }}</div></td>
            @foreach (\App\Enums\Travel\TripKind::cases() as $kind)
                <td><div class="label">{{ $kind->label() }}</div><div class="value">{{ $num($totals['by_kind'][$kind->value]) }} km</div></td>
            @endforeach
            <td><div class="label">{{ __('Privater Anteil') }}</div><div class="value">{{ $totals['private_share'] !== null ? $num($totals['private_share'], 1) . ' %' : '–' }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Datum</th>
                <th class="right">Start-km</th>
                <th class="right">End-km</th>
                <th class="right">km</th>
                <th>Fahrtart</th>
                <th>Ziel</th>
                <th>Zweck</th>
                <th>Fahrer</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                @php($log = $r['log'])
                <tr>
                    <td>{{ $log->date?->fdate() }}</td>
                    <td class="right">{{ $log->odometer_start_km !== null ? $num($log->odometer_start_km) : '–' }}</td>
                    <td class="right">{{ $log->odometer_end_km !== null ? $num($log->odometer_end_km) : '–' }}</td>
                    <td class="right">{{ $num((int) $r['km']) }}</td>
                    <td>{{ $log->trip_kind->label() }}</td>
                    <td>{{ $log->to_address }}</td>
                    <td>{{ $log->purpose }}</td>
                    <td>{{ $log->user?->name ?? '–' }}</td>
                    <td>
                        @if ($r['superseded'])
                            storniert
                        @elseif ($log->isLocked())
                            festgeschrieben {{ $log->locked_at?->format('d.m.Y H:i') }}
                        @else
                            offen
                        @endif
                        @if ($log->isCorrection())
                            <br><span class="small">Stornofahrt: {{ $log->correction_reason }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="3">Gesamt</td>
                <td class="right">{{ $num($totals['km']) }}</td>
                <td colspan="5">
                    @foreach (\App\Enums\Travel\TripKind::cases() as $kind)
                        {{ $kind->label() }}: {{ $num($totals['by_kind'][$kind->value]) }} km &nbsp;
                    @endforeach
                </td>
            </tr>
        </tbody>
    </table>
@endsection
