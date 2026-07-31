{{--
    Print-Balkendiagramm (horizontal) für Report-PDFs — reine HTML/CSS-Tabelle
    (dompdf rendert kein Tailwind-/Pattern-SVG zuverlässig). Balkenlänge als
    %-Breite eines inneren Divs; Werte stehen daneben (Diagramm und
    Kompakttabelle in einem, §Diagramm-UX/PDF).

    Erwartet: $series ([['x','y','y2'?]]), $unit, $yLabel?, $y2Label?, $xLabel?
--}}
@php
    $points = collect($series ?? [])->values();
    $hasSecond = $points->contains(fn(array $p): bool => array_key_exists('y2', $p) && $p['y2'] !== null);
    $maxY = max(1e-9, (float) $points->max('y'), (float) ($hasSecond ? $points->max('y2') : 0));
    $pct = fn(float $v): float => round(max(0, $v) / $maxY * 100, 1);
    $c1 = \App\Support\PrintChartPalette::series(0);
    $c2 = \App\Support\PrintChartPalette::series(1);
    $track = \App\Support\PrintChartPalette::barTrack();
@endphp
@if ($points->isEmpty())
    <p class="small">{{ __('Noch keine Daten für dieses Diagramm.') }}</p>
@else
    <table class="data chart-bars">
        <thead>
            <tr>
                <th style="width: 28%;">{{ $xLabel ?? __('Kategorie') }}</th>
                <th>{{ $yLabel ?? $unit }}</th>
                <th class="right" style="width: 10%;">{{ $yLabel ?? $unit }}</th>
                @if ($hasSecond)<th class="right" style="width: 10%;">{{ $y2Label }}</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach ($points as $point)
                <tr>
                    <td>{{ $point['x'] }}</td>
                    <td>
                        <div style="background: {{ $track }}; width: 100%;">
                            <div style="background: {{ $c1 }}; width: {{ $pct((float) $point['y']) }}%; height: 7px; font-size: 1px;">&nbsp;</div>
                        </div>
                        @if ($hasSecond && ($point['y2'] ?? null) !== null)
                            <div style="background: {{ $track }}; width: 100%; margin-top: 2px;">
                                <div style="background: {{ $c2 }}; width: {{ $pct((float) $point['y2']) }}%; height: 7px; font-size: 1px;">&nbsp;</div>
                            </div>
                        @endif
                    </td>
                    <td class="right">{{ $point['y'] }}</td>
                    @if ($hasSecond)<td class="right">{{ $point['y2'] ?? '–' }}</td>@endif
                </tr>
            @endforeach
        </tbody>
    </table>
    @if ($hasSecond)
        @include('reports.pdf.charts._legend', ['entries' => [
            ['color' => $c1, 'label' => $yLabel ?? $unit],
            ['color' => $c2, 'label' => $y2Label],
        ]])
    @endif
@endif
