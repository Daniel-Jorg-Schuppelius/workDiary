{{--
  Created on   : Fri Jul 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _stacked-bar-h.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Print-Stapelbalken (horizontal) für Report-PDFs: Segmente eines Balkens
    nebeneinander als %-breite Divs (table-cell-Layout, dompdf-robust),
    daneben die Segmentwerte als Kompakttabelle.

    Erwartet: $series ([['x', '<key>' => Zahl, …]]), $bands ([['key','label']]),
              $unit, $xLabel?
--}}
@php
    $points = collect($series ?? [])->values();
    $bandList = collect($bands ?? [])->values();
    $totals = $points->map(fn(array $p): float => (float) $bandList->sum(fn(array $b) => (float) ($p[$b['key']] ?? 0)));
    $maxTotal = max(1e-9, (float) $totals->max());
    $track = \App\Support\PrintChartPalette::barTrack();
@endphp
@if ($points->isEmpty() || $bandList->isEmpty())
    <p class="small">{{ __('Noch keine Daten für dieses Diagramm.') }}</p>
@else
    <table class="data chart-bars">
        <thead>
            <tr>
                <th style="width: 24%;">{{ $xLabel ?? __('Kategorie') }}</th>
                <th>{{ $unit }}</th>
                @foreach ($bandList as $band)<th class="right">{{ $band['label'] }}</th>@endforeach
                <th class="right">Σ</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($points as $i => $point)
                @php($rowTotal = (float) $totals[$i])
                <tr>
                    <td>{{ $point['x'] }}</td>
                    <td>
                        <div style="background: {{ $track }}; width: 100%; font-size: 1px; line-height: 7px;">
                            @foreach ($bandList as $bandIndex => $band)
                                @php($value = (float) ($point[$band['key']] ?? 0))
                                @if ($value > 0)
                                    <div style="display: inline-block; background: {{ \App\Support\PrintChartPalette::series($bandIndex) }}; width: {{ round($value / $maxTotal * 100, 1) }}%; height: 7px;">&nbsp;</div>
                                @endif
                            @endforeach
                        </div>
                    </td>
                    @foreach ($bandList as $band)<td class="right">{{ $point[$band['key']] ?? 0 }}</td>@endforeach
                    <td class="right"><strong>{{ $totals[$i] }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @include('reports.pdf.charts._legend', ['entries' => $bandList->values()->map(fn(array $band, int $i): array => [
        'color' => \App\Support\PrintChartPalette::series($i),
        'label' => $band['label'],
    ])->all()])
@endif
