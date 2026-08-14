{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _waterfall-h.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Print-Waterfall (horizontal) für Report-PDFs: schwebende Balken als
    Offset-Div (margin-left in %), Bestandssäulen in neutralem Grau,
    Δ und kumulierter Stand als Zahlenspalten.

    Erwartet: $series ([['x','y']]), $startValue, $startLabel?, $endLabel?, $unit, $xLabel?
--}}
@php
    $deltas = collect($series ?? [])->values();
    $rows = collect();
    $running = (float) ($startValue ?? 0);
    $rows->push(['x' => $startLabel ?? __('Anfangsbestand'), 'type' => 'total', 'from' => 0.0, 'to' => $running, 'delta' => null]);
    foreach ($deltas as $d) {
        $from = $running;
        $running += (float) $d['y'];
        $rows->push(['x' => $d['x'], 'type' => 'delta', 'from' => $from, 'to' => $running, 'delta' => (float) $d['y']]);
    }
    $rows->push(['x' => $endLabel ?? __('Endbestand'), 'type' => 'total', 'from' => 0.0, 'to' => $running, 'delta' => null]);

    $lo = min(0.0, (float) $rows->min('from'), (float) $rows->min('to'));
    $hi = max($lo + 1e-9, (float) $rows->max('from'), (float) $rows->max('to'));
    $span = $hi - $lo;
    $pos = fn(float $v): float => round(($v - $lo) / $span * 100, 1);
    $cUp = \App\Support\PrintChartPalette::series(0);
    $cDown = \App\Support\PrintChartPalette::series(1);
    $cTotal = \App\Support\PrintChartPalette::axis();
    $track = \App\Support\PrintChartPalette::barTrack();
    $fmt = fn(float $v): string => ($v > 0 ? '+' : ($v < 0 ? '−' : '±')) . rtrim(rtrim(number_format(abs($v), 2, '.', ''), '0'), '.');
    $num = fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
@endphp
@if ($deltas->isEmpty())
    <p class="small">{{ __('Noch keine Daten für dieses Diagramm.') }}</p>
@else
    <table class="data chart-bars">
        <thead>
            <tr>
                <th style="width: 28%;">{{ $xLabel ?? __('Schritt') }}</th>
                <th>{{ $unit }}</th>
                <th class="right" style="width: 12%;">{{ __('Veränderung') }}</th>
                <th class="right" style="width: 12%;">{{ __('Stand') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                @php
                    $left = $pos(min($row['from'], $row['to']));
                    $w = max(0.5, abs($pos($row['to']) - $pos($row['from'])));
                    $color = $row['type'] === 'total' ? $cTotal : ((($row['delta'] ?? 0) < 0) ? $cDown : $cUp);
                @endphp
                <tr>
                    <td>{{ $row['x'] }}</td>
                    <td>
                        <div style="background: {{ $track }}; width: 100%;">
                            <div style="background: {{ $color }}; margin-left: {{ $left }}%; width: {{ $w }}%; height: 7px; font-size: 1px;">&nbsp;</div>
                        </div>
                    </td>
                    <td class="right">{{ $row['delta'] !== null ? $fmt((float) $row['delta']) : '–' }}</td>
                    <td class="right">{{ $num((float) $row['to']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @include('reports.pdf.charts._legend', ['entries' => [
        ['color' => $cUp, 'label' => __('Zunahme')],
        ['color' => $cDown, 'label' => __('Abnahme')],
        ['color' => $cTotal, 'label' => __('Bestand')],
    ]])
@endif
