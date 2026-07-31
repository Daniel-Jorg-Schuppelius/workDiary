{{--
    Print-Heatmap für Report-PDFs: Tabellenmatrix mit vorberechneten
    Hex-Tönungen ({@see \App\Support\PrintChartPalette::heat} — dompdf kennt
    weder CSS-Variablen noch color-mix()). Werte stehen in den Zellen,
    Farbe ist nie alleiniger Träger.

    Erwartet: $rows ([['label','cells' => [null|['value','label'?]]]]),
              $colLabels, $unit, $max?, $xLabel?, $format? (fn(float): string)
--}}
@php
    $rowList = collect($rows ?? [])->values();
    $values = $rowList->flatMap(fn(array $r) => collect($r['cells'] ?? [])->filter()->map(fn(array $c): float => (float) ($c['value'] ?? 0)));
    $maxCell = (float) ($max ?? ($values->max() ?? 0));
    $fmt = $format ?? null;
    $display = function (float $value) use ($fmt): string {
        if ($value <= 0) {
            return '';
        }
        return $fmt !== null ? (string) $fmt($value) : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    };
@endphp
@if ($rowList->isEmpty() || $maxCell <= 0)
    <p class="small">{{ __('Noch keine Daten für dieses Diagramm.') }}</p>
@else
    <table class="chart-heatmap">
        <thead>
            <tr>
                <th>{{ $xLabel ?? '' }}</th>
                @foreach ($colLabels ?? [] as $colLabel)
                    <th class="right">{{ $colLabel }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rowList as $row)
                <tr>
                    <td><strong>{{ $row['label'] }}</strong></td>
                    @foreach ($row['cells'] ?? [] as $cell)
                        @if ($cell === null)
                            <td style="background: #f5f5f5; color: #bbb; text-align: center;">·</td>
                        @else
                            @php($value = (float) ($cell['value'] ?? 0))
                            <td style="background: {{ \App\Support\PrintChartPalette::heat($value, $maxCell) }}; text-align: center;">
                                {{ $cell['label'] ?? $display($value) }}
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="small">{{ __('Färbung skaliert mit dem höchsten Zellwert.') }} ({{ $unit }})</p>
@endif
