{{--
  Created on   : Fri Jul 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : stacked-bar.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Gestapelte Säulen (Komposition je Periode, §Diagramm-UX erzwungen).
     Datenform wie x-charts.area-stack: $series-Zeilen tragen die Band-Keys
     flach, $bands ordnet unten → oben. Max. ~5 Bänder — Aufrufer kappen
     („Rest"-Sammelband). x-charts.bar bleibt unberührt: dessen y2 ist
     Vergleichs-, nicht Kompositionssemantik.

     $series: [['x' => Label, '<key>' => Zahl, …, 'url' => ?Link]]
     $bands : [['key' => 'a', 'label' => …], …] --}}

@props([
    'title',
    'unit',
    'series' => [],
    'bands' => [],           // unten → oben: [['key' => 'billable', 'label' => …], …]
    'computedAt' => null,
    'xLabel' => null,
])

@php
    $points = collect($series)->values();
    $bandList = collect($bands)->values();
    // Ab ~9 Kategorien überlappen horizontale Labels — dann schräg (−40°) mit mehr Fußraum.
    $rotateLabels = $points->count() > 8;
    $width = 640; $pad = 36;
    $padB = $rotateLabels ? 84 : 36;
    $height = 240 + ($rotateLabels ? 48 : 0);
    $totals = $points->map(fn(array $p): float => (float) $bandList->sum(fn(array $b) => (float) ($p[$b['key']] ?? 0)));
    $maxY = max(1, (int) ceil((float) $totals->max()));
    $slot_ = $points->count() > 0 ? ($width - 2 * $pad) / $points->count() : 0;
    $barW = max(6, min(38, $slot_ * 0.55));
    $sy = fn(float $v): float => $height - $padB - ($v / $maxY) * ($height - $pad - $padB);
    $fills = ['fill-primary/70', 'fill-secondary/60', 'fill-accent/50', 'fill-info/50', 'fill-warning/50'];
@endphp

<figure class="rounded-box border border-base-300 bg-base-100 p-3">
    <figcaption>
        <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ $title }}</span>
        <span class="ml-2 text-xs text-base-content/60">
            {{ $unit }}
            @if ($points->isNotEmpty()) · {{ $points->first()['x'] }} – {{ $points->last()['x'] }} @endif
            @if ($computedAt) · {{ __('Stand:') }} {{ \Illuminate\Support\Carbon::parse($computedAt)->isoFormat('L LT') }} @endif
        </span>
    </figcaption>

    @if ($points->isEmpty() || $bandList->isEmpty())
        <x-empty-state icon="stacked_bar_chart" :title="__('Noch keine Daten für dieses Diagramm.')" compact />
    @else
        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $title }}" class="mt-2 w-full">
            <line x1="{{ $pad }}" y1="{{ $height - $padB }}" x2="{{ $width - $pad }}" y2="{{ $height - $padB }}" class="stroke-base-300" stroke-width="1" />
            <line x1="{{ $pad }}" y1="{{ $pad }}" x2="{{ $pad }}" y2="{{ $height - $padB }}" class="stroke-base-300" stroke-width="1" />
            <text x="{{ $pad - 6 }}" y="{{ $pad }}" text-anchor="end" class="fill-base-content/60 text-[10px]">{{ $maxY }}</text>
            <text x="{{ $pad - 6 }}" y="{{ $height - $padB }}" text-anchor="end" class="fill-base-content/60 text-[10px]">0</text>
            @foreach ($points as $i => $point)
                @php
                    $cx = $pad + ($i + 0.5) * $slot_;
                    $ariaSegments = $bandList
                        ->map(fn(array $b): array => ['label' => $b['label'], 'value' => (float) ($point[$b['key']] ?? 0)])
                        ->filter(fn(array $s): bool => $s['value'] > 0)
                        ->map(fn(array $s): string => $s['label'] . ': ' . $s['value'])->implode(', ');
                    // Segment-Rechtecke vorberechnen (unten → oben gestapelt).
                    $rects = [];
                    $stackBase = 0.0;
                    foreach ($bandList as $bandIndex => $band) {
                        $value = (float) ($point[$band['key']] ?? 0);
                        $yTop = $sy($stackBase + $value);
                        $segH = $sy($stackBase) - $yTop;
                        $stackBase += $value;
                        if ($segH > 0) {
                            $rects[] = ['y' => $yTop, 'h' => $segH, 'fill' => $fills[$bandIndex % count($fills)]];
                        }
                    }
                @endphp
                <a @if (!empty($point['url'])) href="{{ $point['url'] }}" @endif tabindex="0"
                   aria-label="{{ $point['x'] }}: {{ $totals[$i] }} {{ $unit }}@if ($ariaSegments !== '') ({{ $ariaSegments }})@endif">
                    @foreach ($rects as $rect)
                        <rect x="{{ round($cx - $barW / 2, 1) }}" y="{{ round($rect['y'], 1) }}"
                              width="{{ round($barW, 1) }}" height="{{ round($rect['h'], 1) }}"
                              class="{{ $rect['fill'] }} stroke-base-100" stroke-width="0.5" />
                    @endforeach
                </a>
                @if ($rotateLabels)
                    <text x="{{ round($cx, 1) }}" y="{{ $height - $padB + 12 }}" text-anchor="end"
                          transform="rotate(-40 {{ round($cx, 1) }} {{ $height - $padB + 12 }})"
                          class="fill-base-content/60 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 18, '…') }}</text>
                @else
                    <text x="{{ round($cx, 1) }}" y="{{ $height - $padB + 12 }}" text-anchor="middle" class="fill-base-content/60 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 10, '…') }}</text>
                @endif
            @endforeach
        </svg>
        <p class="mt-1 flex flex-wrap gap-3 text-xs">
            @foreach ($bandList as $bandIndex => $band)
                <span class="inline-flex items-center gap-1">
                    <svg width="14" height="10" aria-hidden="true"><rect width="14" height="10" class="{{ $fills[$bandIndex % count($fills)] }}" /></svg>
                    {{ $bandIndex + 1 }}. {{ $band['label'] }}
                </span>
            @endforeach
        </p>

        <div class="mt-2 max-h-48 overflow-y-auto">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ $xLabel ?? __('Kategorie') }}</th>
                        @foreach ($bandList as $band)<th class="text-right">{{ $band['label'] }}</th>@endforeach
                        <th class="text-right">Σ</th>
                    </tr>
                </x-slot:head>
                @foreach ($points as $i => $point)
                    <tr>
                        <td>
                            @if (!empty($point['url']))
                                <a href="{{ $point['url'] }}" class="link">{{ $point['x'] }}</a>
                            @else
                                {{ $point['x'] }}
                            @endif
                        </td>
                        @foreach ($bandList as $band)<td class="text-right tabular-nums">{{ $point[$band['key']] ?? 0 }}</td>@endforeach
                        <td class="text-right font-semibold tabular-nums">{{ $totals[$i] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif
</figure>
