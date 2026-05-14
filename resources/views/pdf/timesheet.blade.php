<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Stundenzettel #{{ $timesheet->id }}</title>
<style>
    @page { margin: 18mm 16mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
    h1    { font-size: 16pt; margin: 0 0 4pt 0; }
    h2    { font-size: 12pt; margin: 14pt 0 4pt 0; border-bottom: 1px solid #999; padding-bottom: 2pt; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border-bottom: 1px solid #ccc; padding: 4pt 6pt; vertical-align: top; }
    th    { background: #f3f3f3; text-align: left; }
    .right { text-align: right; }
    .meta  { font-size: 9pt; color: #555; margin-top: 2pt; }
    .totals td { border: 0; padding: 2pt 6pt; }
    .sig    { margin-top: 18pt; }
    .sig img { max-height: 80pt; border: 1px solid #ddd; padding: 4pt; background: #fff; }
    .small  { font-size: 8pt; color: #666; }
    .grid2  { width: 100%; }
    .grid2 td { width: 50%; border: 0; padding: 0 4pt 0 0; vertical-align: top; }
</style>
</head>
<body>

<h1>Stundenzettel</h1>
<div class="meta">
    Datum: <strong>{{ optional($timesheet->work_date)->format('d.m.Y') }}</strong> ·
    Projekt: <strong>{{ $timesheet->project?->name }}</strong> ·
    Mitarbeiter: <strong>{{ $timesheet->user?->name }}</strong> ·
    Status: {{ $timesheet->status }}
</div>

<h2>Zeiteinträge</h2>
<table>
    <thead>
        <tr>
            <th>Start</th><th>Ende</th><th class="right">Pause min</th>
            <th class="right">Dauer</th><th>Art</th><th>Beschreibung</th>
        </tr>
    </thead>
    <tbody>
        @forelse($timesheet->entries as $e)
            @php $h = intdiv((int)$e->minutes, 60); $m = (int)$e->minutes % 60; @endphp
            <tr>
                <td>{{ optional($e->started_at)->format('H:i') }}</td>
                <td>{{ optional($e->ended_at)->format('H:i') }}</td>
                <td class="right">{{ (int) $e->break_minutes }}</td>
                <td class="right">{{ $h }}:{{ str_pad((string)$m,2,'0',STR_PAD_LEFT) }}</td>
                <td>{{ $e->kind }}</td>
                <td>{{ $e->description }}</td>
            </tr>
        @empty
            <tr><td colspan="6">—</td></tr>
        @endforelse
    </tbody>
</table>

@php
    $hT = intdiv((int)$timesheet->total_work_minutes, 60);
    $mT = (int)$timesheet->total_work_minutes % 60;
@endphp
<table class="totals">
    <tr><td class="right">Arbeit gesamt:</td><td class="right" style="width:80pt;"><strong>{{ $hT }}:{{ str_pad((string)$mT,2,'0',STR_PAD_LEFT) }} h</strong></td></tr>
    <tr><td class="right">Pause gesamt:</td><td class="right">{{ (int) $timesheet->total_break_minutes }} min</td></tr>
</table>

<h2>Verbrauchsmaterial</h2>
<table>
    <thead>
        <tr>
            <th>Bezeichnung</th><th class="right">Menge</th><th>Einheit</th>
            <th class="right">EP netto</th><th class="right">Summe netto</th>
        </tr>
    </thead>
    <tbody>
        @forelse($timesheet->materialUsages as $u)
            <tr>
                <td>{{ $u->description }}</td>
                <td class="right">{{ rtrim(rtrim(number_format((float)$u->quantity, 3, ',', '.'), '0'), ',') }}</td>
                <td>{{ $u->unit }}</td>
                <td class="right">{{ $u->unit_price !== null ? number_format((float)$u->unit_price, 4, ',', '.').' €' : '—' }}</td>
                <td class="right">{{ number_format((float)$u->line_total_net, 2, ',', '.') }} €</td>
            </tr>
        @empty
            <tr><td colspan="5">—</td></tr>
        @endforelse
    </tbody>
</table>
<table class="totals">
    <tr><td class="right">Material netto gesamt:</td><td class="right" style="width:80pt;"><strong>{{ number_format((float)$timesheet->total_material_net, 2, ',', '.') }} €</strong></td></tr>
</table>

<div class="sig">
    <h2>Kundenfreigabe</h2>
    <table class="grid2">
        <tr>
            <td>
                <div><strong>{{ $timesheet->customer_name ?: '—' }}</strong>
                    @if($timesheet->customer_role) ({{ $timesheet->customer_role }}) @endif
                </div>
                @if($timesheet->customer_email)<div>{{ $timesheet->customer_email }}</div>@endif
                @if($timesheet->signed_at)
                    <div class="small">Signiert am {{ $timesheet->signed_at->format('d.m.Y H:i') }}
                        @if($timesheet->signed_ip) · IP {{ $timesheet->signed_ip }} @endif
                    </div>
                    <div class="small">SHA-256: {{ $timesheet->signature_hash }}</div>
                @endif
            </td>
            <td>
                @if(! empty($signaturePng))
                    <img src="{{ $signaturePng }}" alt="signature">
                @else
                    <div class="small">— keine Unterschrift —</div>
                @endif
            </td>
        </tr>
    </table>
</div>

@if($timesheet->notes)
    <h2>Notizen</h2>
    <p>{!! nl2br(e($timesheet->notes)) !!}</p>
@endif

</body>
</html>
