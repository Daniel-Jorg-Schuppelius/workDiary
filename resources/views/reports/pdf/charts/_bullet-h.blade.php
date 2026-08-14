{{--
  Created on   : Fri Jul 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _bullet-h.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Print-Bullet (vereinfacht) für Report-PDFs: Ist und Ziel als zwei schmale
    Balken je Zeile (dompdf kann keine überlagerten Marker zuverlässig),
    Erreichung als Zahlenspalte — Diagramm und Kompakttabelle in einem.

    Erwartet: $series ([['x','y','target'?]]), $unit, $xLabel?, $yLabel?, $targetLabel?
--}}
@php
    $points = collect($series ?? [])->values();
    $targetLabel = $targetLabel ?? __('Ziel');
    $yLabel = $yLabel ?? __('Ist');
    $maxY = max(1e-9, (float) $points->max('y'), (float) $points->max('target'));
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
                <th>{{ $yLabel }} / {{ $targetLabel }}</th>
                <th class="right" style="width: 10%;">{{ $yLabel }}</th>
                <th class="right" style="width: 10%;">{{ $targetLabel }}</th>
                <th class="right" style="width: 12%;">{{ __('Erreichung') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($points as $point)
                @php($target = $point['target'] ?? null)
                <tr>
                    <td>{{ $point['x'] }}</td>
                    <td>
                        <div style="background: {{ $track }}; width: 100%;">
                            <div style="background: {{ $c1 }}; width: {{ $pct((float) $point['y']) }}%; height: 7px; font-size: 1px;">&nbsp;</div>
                        </div>
                        @if ($target !== null)
                            <div style="background: {{ $track }}; width: 100%; margin-top: 2px;">
                                <div style="background: {{ $c2 }}; width: {{ $pct((float) $target) }}%; height: 7px; font-size: 1px;">&nbsp;</div>
                            </div>
                        @endif
                    </td>
                    <td class="right">{{ $point['y'] }}</td>
                    <td class="right">{{ $target ?? '–' }}</td>
                    <td class="right">{{ $target ? round((float) $point['y'] / (float) $target * 100) . '%' : '–' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @include('reports.pdf.charts._legend', ['entries' => [
        ['color' => $c1, 'label' => $yLabel],
        ['color' => $c2, 'label' => $targetLabel],
    ]])
@endif
