{{--
  Created on   : Fri Jul 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _chart.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Dispatcher der Print-Diagramme: hält die Report-PDF-Views einzeilig.
    Die Chart-Daten kommen unverändert aus dem Controller (gleiche Arrays
    wie am Bildschirm) — nur das Markup ist print-spezifisch.

    Erwartet: $chart = [
        'type'    => 'bar-h' | 'stacked-bar-h' | 'heatmap' | 'bullet-h' | 'waterfall-h' | 'boxplot-table',
        'title'   => string, 'unit' => string,
        'note'    => ?string  (z. B. „vereinfachte Druckdarstellung"),
        …typspezifische Keys (series/bands bzw. rows/colLabels/max/format),
    ]
--}}
@php($chart = $chart ?? null)
@if (is_array($chart))
    <h2>{{ $chart['title'] }}</h2>
    @if (!empty($chart['note']))
        <p class="small">{{ $chart['note'] }}</p>
    @endif
    @if ($chart['type'] === 'bar-h')
        @include('reports.pdf.charts._bar-h', [
            'series' => $chart['series'] ?? [],
            'unit' => $chart['unit'] ?? '',
            'xLabel' => $chart['xLabel'] ?? null,
            'yLabel' => $chart['yLabel'] ?? null,
            'y2Label' => $chart['y2Label'] ?? null,
        ])
    @elseif ($chart['type'] === 'stacked-bar-h')
        @include('reports.pdf.charts._stacked-bar-h', [
            'series' => $chart['series'] ?? [],
            'bands' => $chart['bands'] ?? [],
            'unit' => $chart['unit'] ?? '',
            'xLabel' => $chart['xLabel'] ?? null,
        ])
    @elseif ($chart['type'] === 'heatmap')
        @include('reports.pdf.charts._heatmap', [
            'rows' => $chart['rows'] ?? [],
            'colLabels' => $chart['colLabels'] ?? [],
            'unit' => $chart['unit'] ?? '',
            'max' => $chart['max'] ?? null,
            'xLabel' => $chart['xLabel'] ?? null,
            'format' => $chart['format'] ?? null,
        ])
    @elseif ($chart['type'] === 'bullet-h')
        @include('reports.pdf.charts._bullet-h', [
            'series' => $chart['series'] ?? [],
            'unit' => $chart['unit'] ?? '',
            'xLabel' => $chart['xLabel'] ?? null,
            'yLabel' => $chart['yLabel'] ?? null,
            'targetLabel' => $chart['targetLabel'] ?? null,
        ])
    @elseif ($chart['type'] === 'waterfall-h')
        @include('reports.pdf.charts._waterfall-h', [
            'series' => $chart['series'] ?? [],
            'startValue' => $chart['startValue'] ?? 0,
            'startLabel' => $chart['startLabel'] ?? null,
            'endLabel' => $chart['endLabel'] ?? null,
            'unit' => $chart['unit'] ?? '',
            'xLabel' => $chart['xLabel'] ?? null,
        ])
    @elseif ($chart['type'] === 'boxplot-table')
        @include('reports.pdf.charts._boxplot-table', [
            'series' => $chart['series'] ?? [],
            'unit' => $chart['unit'] ?? '',
            'xLabel' => $chart['xLabel'] ?? null,
        ])
    @endif
@endif
