{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : bullet.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Bullet-Diagramm (MVP-464, §Diagramm-UX erzwungen): Ist-Wert gegen Ziel
     und Leistungsbänder, eine Zeile je Kennzahl/Person — kompakter und
     vergleichbarer als Gauges. Ziel-Marker zusätzlich zur Farbe als Form
     (vertikale Linie), Bänder in neutralen Base-Tönen.

     $series: [['x' => Label, 'y' => Ist, 'target' => ?Ziel,
                'bands' => ?[schlecht, mittel] (aufsteigend, in $unit),
                'url' => ?Drilldown]]. --}}

@props([
    'title',
    'unit',
    'series' => [],
    'computedAt' => null,
    'note' => null,           // Datenbasis-Hinweis unter dem Titel (MVP-470)
    'xLabel' => null,
    'yLabel' => null,
    'targetLabel' => null,
])

@php
    $points = collect($series)->values();
    $targetLabel ??= __('Ziel');
    $width = 640; $labelW = 200; $padR = 52; $padTop = 8; $padBottom = 18;
    $rowH = 30; $bandH = 16; $barH = 6;
    $height = $padTop + $points->count() * $rowH + $padBottom;
    $areaW = $width - $labelW - $padR;
    $maxY = max(
        1,
        (float) $points->max('y'),
        (float) $points->max('target'),
        (float) $points->max(fn (array $p): float => (float) max(array_merge([0], (array) ($p['bands'] ?? [])))),
    );
    $len = fn(float $v): float => max(0, min(1, $v / $maxY)) * $areaW;

    // Kontrakt für die optionale Chart.js-Verbesserung am Bildschirm (charts.js).
    $chartSpec = [
        'type' => 'bar',
        'bullet' => true,
        'title' => $title,
        'unit' => $unit,
        'yLabel' => $yLabel ?? $unit,
        'targetLabel' => (string) $targetLabel,
        'labels' => $points->map(fn(array $p): string => (string) $p['x'])->all(),
        'urls' => $points->map(fn(array $p) => $p['url'] ?? null)->all(),
        'values' => $points->map(fn(array $p): float => (float) $p['y'])->all(),
        'targets' => $points->map(fn(array $p) => ($p['target'] ?? null) === null ? null : (float) $p['target'])->all(),
    ];
@endphp

<x-charts.frame :title="$title" :unit="$unit" :computed-at="$computedAt" :note="$note"
                :empty="$points->isEmpty()"
                :spec="$chartSpec" :view-box="[$width, $height]">
    <line x1="{{ $labelW }}" y1="{{ $padTop }}" x2="{{ $labelW }}" y2="{{ $height - $padBottom }}" class="stroke-base-300" stroke-width="1" />
    @foreach ($points as $i => $point)
        @php
            $rowY = $padTop + $i * $rowH;
            $bandY = round($rowY + ($rowH - $bandH) / 2, 1);
            $bands = array_values(array_filter((array) ($point['bands'] ?? []), fn($b): bool => $b !== null));
            $target = $point['target'] ?? null;
        @endphp
        <a @if (!empty($point['url'])) href="{{ $point['url'] }}" @endif tabindex="0"
           aria-label="{{ $point['x'] }}: {{ $point['y'] }} {{ $unit }}@if ($target !== null), {{ $targetLabel }}: {{ $target }} {{ $unit }}@endif">
            <text x="{{ $labelW - 6 }}" y="{{ round($rowY + $rowH / 2 + 3, 1) }}" text-anchor="end" class="fill-base-content/80 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 30, '…') }}</text>
            <rect x="{{ $labelW }}" y="{{ $bandY }}" width="{{ round($areaW, 1) }}" height="{{ $bandH }}" class="fill-base-200/60" />
            @if (count($bands) > 1)
                <rect x="{{ $labelW }}" y="{{ $bandY }}" width="{{ round($len((float) $bands[1]), 1) }}" height="{{ $bandH }}" class="fill-base-300/60" />
            @endif
            @if (count($bands) > 0)
                <rect x="{{ $labelW }}" y="{{ $bandY }}" width="{{ round($len((float) $bands[0]), 1) }}" height="{{ $bandH }}" class="fill-base-300" />
            @endif
            <rect x="{{ $labelW }}" y="{{ round($rowY + ($rowH - $barH) / 2, 1) }}"
                  width="{{ round($len((float) $point['y']), 1) }}" height="{{ $barH }}"
                  class="fill-primary" />
            @if ($target !== null)
                <rect x="{{ round($labelW + $len((float) $target) - 1, 1) }}" y="{{ round($rowY + ($rowH - $bandH) / 2 - 2, 1) }}"
                      width="2" height="{{ $bandH + 4 }}" class="fill-secondary" />
            @endif
            <text x="{{ round($labelW + $areaW + 4, 1) }}" y="{{ round($rowY + $rowH / 2 + 3, 1) }}" class="fill-base-content/70 text-[10px] tabular-nums">{{ $point['y'] }}</text>
        </a>
    @endforeach
    <text x="{{ $width - $padR + 48 }}" y="{{ $height - 4 }}" text-anchor="end" class="fill-muted text-[10px]">{{ __('Max.') }} {{ $maxY }}</text>

    <x-slot:legend>
        <p class="mt-1 flex flex-wrap gap-3 text-xs">
            <span class="inline-flex items-center gap-1">
                <svg width="14" height="10" aria-hidden="true"><rect y="2" width="14" height="6" class="fill-primary" /></svg>
                {{ $yLabel ?? __('Ist') }}
            </span>
            <span class="inline-flex items-center gap-1">
                <svg width="14" height="10" aria-hidden="true"><rect x="6" width="2" height="10" class="fill-secondary" /></svg>
                {{ $targetLabel }}
            </span>
        </p>
    </x-slot:legend>

    <x-slot:head>
        <tr>
            <th>{{ $xLabel ?? __('Kategorie') }}</th>
            <th class="text-right">{{ $yLabel ?? __('Ist') }}</th>
            <th class="text-right">{{ $targetLabel }}</th>
            <th class="text-right">{{ __('Erreichung') }}</th>
        </tr>
    </x-slot:head>
    <x-slot:rows>
        @foreach ($points as $point)
            @php
                $target = $point['target'] ?? null;
            @endphp
            <tr>
                <td>
                    @if (!empty($point['url']))
                        <a href="{{ $point['url'] }}" class="link">{{ $point['x'] }}</a>
                    @else
                        {{ $point['x'] }}
                    @endif
                </td>
                <td class="text-right tabular-nums">{{ $point['y'] }}</td>
                <td class="text-right tabular-nums">{{ $target ?? '—' }}</td>
                <td class="text-right tabular-nums">{{ $target ? round((float) $point['y'] / (float) $target * 100) . '%' : '—' }}</td>
            </tr>
        @endforeach
    </x-slot:rows>
</x-charts.frame>
