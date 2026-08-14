{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Reisekostenabrechnung – {{ $trip->location }} ({{ $trip->started_at->fdate() }})</title>
<style>
    @page { margin: 14mm 12mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
    h1    { font-size: 14pt; margin: 0 0 4pt 0; }
    h2    { font-size: 11pt; margin: 12pt 0 4pt 0; color: #333; border-bottom: 1px solid #ccc; padding-bottom: 2pt; }
    .meta { font-size: 9pt; color: #555; margin-bottom: 10pt; }
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
    .header-table td { padding: 2pt 4pt; vertical-align: top; }
    .header-table td.label { color: #666; width: 28%; }
    .kpis { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
    .kpis td { padding: 4pt 6pt; border: 1px solid #ccc; background: #f7f7f7; width: 25%; }
    .kpis .label { font-size: 8pt; color: #666; }
    .kpis .value { font-size: 12pt; font-weight: bold; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border-bottom: 1px solid #ccc; padding: 3pt 5pt; vertical-align: top; }
    table.data th { background: #f3f3f3; text-align: left; font-size: 8.5pt; }
    .right { text-align: right; }
    .center { text-align: center; }
    .totals td { border-top: 2px solid #333; font-weight: bold; }
    .muted { color: #888; }
    .note { font-size: 8pt; color: #666; margin-top: 4pt; }
    .footer { position: fixed; bottom: 6mm; left: 12mm; right: 12mm; font-size: 7.5pt; color: #888; border-top: 1px solid #ddd; padding-top: 2pt; }
    .sig { margin-top: 24pt; width: 100%; border-collapse: collapse; }
    .sig td { width: 50%; padding: 24pt 6pt 2pt 6pt; border-top: 1px solid #555; font-size: 8pt; color: #555; }
</style>
</head>
<body>
@php
    $fmtEur = static function ($value): string {
        return \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(
            $value instanceof \CommonToolkit\ValueObjects\Money ? $value->toFloat() : (float) $value,
            2,
            withThousandsSeparator: true
        ) . ' €';
    };
    $totalBase = $trip->days->sum(fn ($d) => $d->base_amount?->toFloat() ?? 0.0);
    $totalDeductions = $trip->days->sum(fn ($d) => $d->deductions_total?->toFloat() ?? 0.0);
    $totalPayout = $trip->days->sum(fn ($d) => $d->amount?->toFloat() ?? 0.0);
@endphp

<h1>{{ __('Reisekostenabrechnung – Verpflegungspauschale') }}</h1>
<div class="meta">
    Reise #{{ $trip->id }} · Status: <strong>{{ $trip->status->label() }}</strong> ·
    Erstellt: {{ now()->fdatetime() }}
</div>

<table class="header-table">
    <tr>
        <td class="label">Mitarbeiter</td>
        <td><strong>{{ $trip->user?->name }}</strong>@if ($trip->user?->email) &nbsp;<span class="muted">&lt;{{ $trip->user->email }}&gt;</span>@endif</td>
        <td class="label">Land</td>
        <td><strong>{{ $trip->country }}</strong></td>
    </tr>
    <tr>
        <td class="label">{{ __('Ort / Ziel') }}</td>
        <td>{{ $trip->location }}</td>
        <td class="label">Zeitraum</td>
        <td>{{ $trip->started_at->fdatetime() }} – {{ $trip->ended_at->fdatetime() }}</td>
    </tr>
    <tr>
        <td class="label">Zweck</td>
        <td colspan="3">{{ $trip->purpose }}</td>
    </tr>
    @if ($trip->project)
    <tr>
        <td class="label">Projekt</td>
        <td>{{ $trip->project->name }}</td>
        <td class="label">Kunde</td>
        <td>{{ $trip->customer?->name ?? '—' }}</td>
    </tr>
    @endif
    <tr>
        <td class="label">{{ __('Übernachtung gestellt') }}</td>
        <td>{{ $trip->accommodation_provided ? 'Ja' : 'Nein' }}</td>
        <td class="label">Reisetage</td>
        <td>{{ $trip->days->count() }}</td>
    </tr>
</table>

<table class="kpis">
    <tr>
        <td><div class="label">Pauschale (Basis)</div><div class="value">{{ $fmtEur($totalBase) }}</div></td>
        <td><div class="label">{{ __('Mahlzeitenkürzung') }}</div><div class="value">@if ($totalDeductions > 0)− {{ $fmtEur($totalDeductions) }}@else —@endif</div></td>
        <td><div class="label">Auszahlbetrag</div><div class="value">{{ $fmtEur($totalPayout) }}</div></td>
        <td><div class="label">Tage</div><div class="value">{{ $trip->days->count() }}</div></td>
    </tr>
</table>

<h2>Tagesabrechnung</h2>
<table class="data">
    <thead>
        <tr>
            <th>Datum</th>
            <th>Art</th>
            <th class="right">Pauschale</th>
            <th class="center">F</th>
            <th class="center">M</th>
            <th class="center">A</th>
            <th class="right">{{ __('Kürzung') }}</th>
            <th class="right">Auszahlung</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($trip->days as $day)
            <tr>
                <td>{{ $day->date->fdate() }}</td>
                <td>{{ $day->kind->label() }}</td>
                <td class="right">{{ $fmtEur($day->base_amount) }}</td>
                <td class="center">{{ $day->meal_breakfast ? '×' : '' }}</td>
                <td class="center">{{ $day->meal_lunch ? '×' : '' }}</td>
                <td class="center">{{ $day->meal_dinner ? '×' : '' }}</td>
                <td class="right">@if (($day->deductions_total?->toFloat() ?? 0.0) > 0)− {{ $fmtEur($day->deductions_total) }}@else —@endif</td>
                <td class="right"><strong>{{ $fmtEur($day->amount) }}</strong></td>
            </tr>
        @endforeach
        <tr class="totals">
            <td colspan="2">Gesamt</td>
            <td class="right">{{ $fmtEur($totalBase) }}</td>
            <td colspan="3"></td>
            <td class="right">@if ($totalDeductions > 0)− {{ $fmtEur($totalDeductions) }}@else —@endif</td>
            <td class="right">{{ $fmtEur($totalPayout) }}</td>
        </tr>
    </tbody>
</table>

<div class="note">
    {{ __('F = Frühstück, M = Mittagessen, A = Abendessen. Eine vom Arbeitgeber oder Dritten gestellte Mahlzeit mindert die Pauschale gemäß § 9 Abs. 4a Satz 8 EStG (Frühstück 20 %, Mittag- und Abendessen je 40 % des Volltagessatzes).') }}
</div>

@if ($trip->notes)
    <h2>Anmerkungen</h2>
    <div style="white-space: pre-line;">{{ $trip->notes }}</div>
@endif

<table class="sig">
    <tr>
        <td>{{ __('Datum, Unterschrift Mitarbeiter') }}</td>
        <td>{{ __('Datum, Unterschrift Vorgesetzte/r') }}</td>
    </tr>
</table>

<div class="footer">
    Erstellt mit workDiary · {{ now()->fdatetime() }} · Reise #{{ $trip->id }}
</div>

</body>
</html>
