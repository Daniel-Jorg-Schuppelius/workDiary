{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : boxplot.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Boxplot (MVP-464, §Diagramm-UX erzwungen), horizontal — eine Zeile je
     Kategorie. Die fünf Kennwerte kommen VORBERECHNET aus dem Builder
     (die Komponente rechnet keine Quantile). Whisker Min–Max, Box Q1–Q3,
     Median als starke Linie.

     $series: [['x' => Label, 'min','q1','median','q3','max' => Zahlen,
                'n' => ?Anzahl, 'url' => ?Drilldown]]. --}}

@props([
    'title',
    'unit',
    'series' => [],
    'computedAt' => null,
    'note' => null,           // Datenbasis-Hinweis unter dem Titel (MVP-470)
    'xLabel' => null,
    'yLabel' => null,
])

@php
    $points = collect($series)->values();
    $width = 640; $labelW = 200; $padR = 52; $padTop = 8; $padBottom = 22;
    $rowH = 30; $boxH = 12;
    $height = $padTop + $points->count() * $rowH + $padBottom;
    $areaW = $width - $labelW - $padR;
    $lo = (float) min(0, $points->min('min') ?? 0);
    $hi = max($lo + 1, (float) ($points->max('max') ?? 1));
    $sx = fn(float $v): float => $labelW + (($v - $lo) / ($hi - $lo)) * $areaW;
    $num = fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
@endphp

<figure class="rounded-box border border-base-300 bg-base-100 p-3">
    <figcaption>
        <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ $title }}</span>
        <span class="ml-2 text-xs text-base-content/60">
            {{ $unit }}
            @if ($computedAt) · {{ __('Stand:') }} {{ \Illuminate\Support\Carbon::parse($computedAt)->isoFormat('L LT') }} @endif
        </span>
    </figcaption>

    @if ($note)
        <p class="mt-1 text-xs text-base-content/50">{{ $note }}</p>
    @endif

    @if ($points->isEmpty())
        <div class="wd-chart-empty">
            <x-empty-state icon="candlestick_chart" :title="__('Noch keine Daten für dieses Diagramm.')" compact />
        </div>
    @else
        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $title }}" class="mt-2 w-full">
            <line x1="{{ $labelW }}" y1="{{ $padTop }}" x2="{{ $labelW }}" y2="{{ $height - $padBottom }}" class="stroke-base-300" stroke-width="1" />
            <line x1="{{ $labelW }}" y1="{{ $height - $padBottom }}" x2="{{ $labelW + $areaW }}" y2="{{ $height - $padBottom }}" class="stroke-base-300" stroke-width="1" />
            <text x="{{ $labelW }}" y="{{ $height - $padBottom + 12 }}" text-anchor="middle" class="fill-base-content/60 text-[10px]">{{ $num($lo) }}</text>
            <text x="{{ $labelW + $areaW }}" y="{{ $height - $padBottom + 12 }}" text-anchor="middle" class="fill-base-content/60 text-[10px]">{{ $num($hi) }}</text>
            @foreach ($points as $i => $point)
                @php
                    $cy = round($padTop + $i * $rowH + $rowH / 2, 1);
                    $xMin = round($sx((float) $point['min']), 1);
                    $xQ1 = round($sx((float) $point['q1']), 1);
                    $xMed = round($sx((float) $point['median']), 1);
                    $xQ3 = round($sx((float) $point['q3']), 1);
                    $xMax = round($sx((float) $point['max']), 1);
                @endphp
                <a @if (!empty($point['url'])) href="{{ $point['url'] }}" @endif tabindex="0"
                   aria-label="{{ $point['x'] }}: {{ __('Median') }} {{ $num((float) $point['median']) }} {{ $unit }}, {{ __('Quartile') }} {{ $num((float) $point['q1']) }}–{{ $num((float) $point['q3']) }}, {{ __('Spannweite') }} {{ $num((float) $point['min']) }}–{{ $num((float) $point['max']) }}@if (!empty($point['n'])), n={{ $point['n'] }}@endif">
                    <text x="{{ $labelW - 6 }}" y="{{ $cy + 3 }}" text-anchor="end" class="fill-base-content/80 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 30, '…') }}</text>
                    <line x1="{{ $xMin }}" y1="{{ $cy }}" x2="{{ $xMax }}" y2="{{ $cy }}" class="stroke-base-content/40" stroke-width="1" />
                    <line x1="{{ $xMin }}" y1="{{ $cy - 5 }}" x2="{{ $xMin }}" y2="{{ $cy + 5 }}" class="stroke-base-content/40" stroke-width="1" />
                    <line x1="{{ $xMax }}" y1="{{ $cy - 5 }}" x2="{{ $xMax }}" y2="{{ $cy + 5 }}" class="stroke-base-content/40" stroke-width="1" />
                    <rect x="{{ $xQ1 }}" y="{{ round($cy - $boxH / 2, 1) }}" width="{{ round(max(1, $xQ3 - $xQ1), 1) }}" height="{{ $boxH }}"
                          class="fill-primary/30 stroke-primary" stroke-width="1" />
                    <line x1="{{ $xMed }}" y1="{{ round($cy - $boxH / 2 - 2, 1) }}" x2="{{ $xMed }}" y2="{{ round($cy + $boxH / 2 + 2, 1) }}" class="stroke-primary" stroke-width="2" />
                </a>
            @endforeach
        </svg>

        <div class="wd-chart-table mt-2 max-h-48 overflow-y-auto">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ $xLabel ?? __('Kategorie') }}</th>
                        <th class="text-right">{{ __('Min') }}</th>
                        <th class="text-right">{{ __('Q1') }}</th>
                        <th class="text-right">{{ __('Median') }}</th>
                        <th class="text-right">{{ __('Q3') }}</th>
                        <th class="text-right">{{ __('Max') }}</th>
                        <th class="text-right">n</th>
                    </tr>
                </x-slot:head>
                @foreach ($points as $point)
                    <tr>
                        <td>
                            @if (!empty($point['url']))
                                <a href="{{ $point['url'] }}" class="link">{{ $point['x'] }}</a>
                            @else
                                {{ $point['x'] }}
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $num((float) $point['min']) }}</td>
                        <td class="text-right tabular-nums">{{ $num((float) $point['q1']) }}</td>
                        <td class="text-right tabular-nums">{{ $num((float) $point['median']) }}</td>
                        <td class="text-right tabular-nums">{{ $num((float) $point['q3']) }}</td>
                        <td class="text-right tabular-nums">{{ $num((float) $point['max']) }}</td>
                        <td class="text-right tabular-nums">{{ $point['n'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif
</figure>
