{{--
  Created on   : Fri Jul 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : heatmap.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Matrix-Heatmap (§Diagramm-UX erzwungen): Tabellenmatrix mit
     color-mix()-Primärton-Intensität (generalisiert aus „Mein Jahr").
     Als Tabelle ist die Heatmap ihr eigenes Tabellenäquivalent; Werte
     stehen sichtbar in den Zellen (Farbe nie alleiniger Träger).

     $rows: [['label' => …, 'url' => ?, 'cells' => [ null | ['value' => Zahl,
             'label' => ?Anzeige, 'title' => ?Tooltip, 'url' => ?, 'class' => ?] ]]]
     null-Zellen sind „nicht vorhanden" (z. B. 31. Februar).
     $format: optionaler Callable für Zell-/Summen-Anzeige (Zahl → String). --}}

@props([
    'title',
    'unit',
    'rows' => [],
    'colLabels' => [],
    'computedAt' => null,
    'max' => null,           // Skalenmaximum; Default: höchster Zellwert
    'totals' => true,        // Σ-Spalte/-Zeile anzeigen
    'xLabel' => null,        // Kopf der Zeilenlabel-Spalte
    'format' => null,        // fn(float): string
])

@php
    $rowList = collect($rows)->values();
    $values = $rowList->flatMap(fn(array $r) => collect($r['cells'] ?? [])->filter()->map(fn(array $c): float => (float) ($c['value'] ?? 0)));
    $maxCell = (float) ($max ?? ($values->max() ?? 0));
    $display = function (float $value) use ($format): string {
        if ($value <= 0) {
            return '';
        }
        return $format !== null ? (string) $format($value) : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    };
    // Skala 8% .. 60% Primary-Tönung (wie „Mein Jahr").
    $intensity = function (float $value) use ($maxCell): string {
        if ($value <= 0 || $maxCell <= 0) {
            return '';
        }
        $alpha = 8 + (int) round(min(1.0, $value / $maxCell) * 52);
        return 'background-color: color-mix(in oklab, var(--color-primary) ' . $alpha . '%, transparent);';
    };
    $rowTotals = $rowList->map(fn(array $r): float => collect($r['cells'] ?? [])->filter()->sum(fn(array $c): float => (float) ($c['value'] ?? 0)));
    $colCount = max(count($colLabels), (int) $rowList->max(fn(array $r) => count($r['cells'] ?? [])));
    $colTotals = collect(range(0, max(0, $colCount - 1)))->map(
        fn(int $col): float => $rowList->sum(fn(array $r): float => (float) (($r['cells'][$col] ?? null)['value'] ?? 0))
    );
    $grandTotal = (float) $rowTotals->sum();
@endphp

<figure class="rounded-box border border-base-300 bg-base-100 p-3">
    <figcaption>
        <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ $title }}</span>
        <span class="ml-2 text-xs text-base-content/60">
            {{ $unit }}
            @if ($computedAt) · {{ __('Stand:') }} {{ \Illuminate\Support\Carbon::parse($computedAt)->isoFormat('L LT') }} @endif
        </span>
    </figcaption>

    @if ($rowList->isEmpty() || $maxCell <= 0)
        <x-empty-state icon="grid_on" :title="__('Noch keine Daten für dieses Diagramm.')" compact />
    @else
        <div class="mt-2 overflow-x-auto">
            <table class="table table-xs w-full text-center tabular-nums">
                <thead>
                    <tr>
                        <th class="text-left font-semibold uppercase tracking-[0.12em] text-[0.65rem] text-base-content/60">{{ $xLabel ?? '' }}</th>
                        @foreach ($colLabels as $colLabel)
                            <th class="px-1 font-semibold text-[0.65rem] text-base-content/50">{{ $colLabel }}</th>
                        @endforeach
                        @if ($totals)
                            <th class="bg-base-200 px-2 font-semibold uppercase tracking-[0.12em] text-[0.65rem] text-base-content/70">Σ</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rowList as $rowIndex => $row)
                        <tr>
                            <th class="whitespace-nowrap text-left font-semibold text-base-content/80">
                                @if (!empty($row['url']))
                                    <a href="{{ $row['url'] }}" class="link link-hover">{{ $row['label'] }}</a>
                                @else
                                    {{ $row['label'] }}
                                @endif
                            </th>
                            @foreach ($row['cells'] ?? [] as $cell)
                                @if ($cell === null)
                                    <td class="bg-base-200/40 text-base-content/30">·</td>
                                @else
                                    @php
                                        $value = (float) ($cell['value'] ?? 0);
                                        $text = $cell['label'] ?? $display($value);
                                    @endphp
                                    <td class="text-[0.65rem] {{ $cell['class'] ?? '' }}" style="{{ $intensity($value) }}"
                                        @if (!empty($cell['title'])) title="{{ $cell['title'] }}" @endif>
                                        @if (!empty($cell['url']) && $value > 0)
                                            <a href="{{ $cell['url'] }}" tabindex="0" class="link link-hover"
                                               aria-label="{{ $cell['title'] ?? ($row['label'] . ': ' . $text . ' ' . $unit) }}">{{ $text }}</a>
                                        @else
                                            {{ $text }}
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                            @if ($totals)
                                <td class="bg-base-200 font-semibold {{ $rowTotals[$rowIndex] > 0 ? 'text-base-content' : 'text-base-content/40' }}">
                                    {{ $rowTotals[$rowIndex] > 0 ? $display($rowTotals[$rowIndex]) : '·' }}
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                @if ($totals)
                    <tfoot>
                        <tr>
                            <th class="bg-base-200 text-left text-[0.65rem] uppercase tracking-[0.12em] text-base-content/70">Σ</th>
                            @foreach ($colTotals as $colTotal)
                                <th class="bg-base-200 text-[0.65rem] {{ $colTotal > 0 ? 'text-base-content' : 'text-base-content/40' }}">
                                    {{ $colTotal > 0 ? $display($colTotal) : '' }}
                                </th>
                            @endforeach
                            <th class="bg-primary/10 font-semibold text-primary">{{ $display($grandTotal) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
</figure>
