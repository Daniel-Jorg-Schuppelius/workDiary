{{--
  Created on   : Fri Jul 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : bar-h.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Horizontale Balken / Top-N (§Diagramm-UX erzwungen): volle Kategorie-
     Labels links (x-charts.bar kürzt auf 10 Zeichen — für Kunden-/Projekt-
     namen unbrauchbar), Wert am Balkenende, Schraffur-Zweitserie, Höhe
     wächst mit der Zeilenzahl.

     $series: Liste von ['x' => Label, 'y' => Zahl, 'y2' => ?Zahl, 'url' => ?Link]. --}}

@props([
    'title',
    'unit',
    'series' => [],
    'computedAt' => null,
    'note' => null,           // Datenbasis-Hinweis unter dem Titel (MVP-470)
    'xLabel' => null,
    'yLabel' => null,
    'y2Label' => null,       // Label der optionalen Zweitserie
])

@php
    $points = collect($series)->values();
    $hasSecond = $points->contains(fn(array $p): bool => array_key_exists('y2', $p) && $p['y2'] !== null);
    $width = 640; $labelW = 200; $padR = 52; $padTop = 8; $padBottom = 18;
    $rowH = $hasSecond ? 40 : 26;
    $barH = $hasSecond ? 12 : 14;
    $height = $padTop + $points->count() * $rowH + $padBottom;
    $areaW = $width - $labelW - $padR;
    $maxY = max(1, (float) $points->max('y'), (float) ($hasSecond ? $points->max('y2') : 0));
    $len = fn(float $v): float => max(0, $v / $maxY) * $areaW;
    $uid = 'hatch-h-' . uniqid();

    // Kontrakt für die optionale Chart.js-Verbesserung am Bildschirm (charts.js).
    $chartDatasets = [[
        'label' => $yLabel ?? $unit,
        'data' => $points->map(fn(array $p): float => (float) $p['y'])->all(),
        'kind' => 'bar', 'role' => 'primary',
    ]];
    if ($hasSecond) {
        $chartDatasets[] = [
            'label' => $y2Label ?? __('Zweitwert'),
            'data' => $points->map(fn(array $p) => ($p['y2'] ?? null) === null ? null : (float) $p['y2'])->all(),
            'kind' => 'bar', 'role' => 'second', 'hatch' => true,
        ];
    }
    $chartSpec = [
        'type' => 'bar',
        'horizontal' => true,
        'title' => $title,
        'unit' => $unit,
        'xLabel' => $xLabel,
        'yLabel' => $yLabel ?? $unit,
        'labels' => $points->map(fn(array $p): string => (string) $p['x'])->all(),
        'urls' => $points->map(fn(array $p) => $p['url'] ?? null)->all(),
        'datasets' => $chartDatasets,
    ];
@endphp

<figure class="wd-chart rounded-box border border-base-300 bg-base-100 p-3">
    <figcaption>
        <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ $title }}</span>
        <span class="ml-2 text-xs text-muted">
            {{ $unit }}
            @if ($computedAt) · {{ __('Stand:') }} {{ \Illuminate\Support\Carbon::parse($computedAt)->isoFormat('L LT') }} @endif
        </span>
    </figcaption>

    @if ($note)
        <p class="mt-1 text-xs text-muted">{{ $note }}</p>
    @endif

    @if ($points->isEmpty())
        <div class="wd-chart-empty">
            <x-empty-state icon="bar_chart" :title="__('Noch keine Daten für dieses Diagramm.')" compact />
        </div>
    @else
        @include('components.charts._canvas', ['spec' => $chartSpec])
        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $title }}" class="wd-chart-svg mt-2 w-full">
            <defs>
                <pattern id="{{ $uid }}" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                    <line x1="0" y1="0" x2="0" y2="6" class="stroke-secondary" stroke-width="2" />
                </pattern>
            </defs>
            <line x1="{{ $labelW }}" y1="{{ $padTop }}" x2="{{ $labelW }}" y2="{{ $height - $padBottom }}" class="stroke-base-300" stroke-width="1" />
            @foreach ($points as $i => $point)
                @php($rowY = $padTop + $i * $rowH)
                <a @if (!empty($point['url'])) href="{{ $point['url'] }}" @endif tabindex="0"
                   aria-label="{{ $point['x'] }}: {{ $point['y'] }} {{ $unit }}@if ($hasSecond && ($point['y2'] ?? null) !== null), {{ $y2Label }}: {{ $point['y2'] }}@endif">
                    <text x="{{ $labelW - 6 }}" y="{{ round($rowY + $rowH / 2 + 3, 1) }}" text-anchor="end" class="fill-base-content/80 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 30, '…') }}</text>
                    <rect x="{{ $labelW }}" y="{{ round($rowY + ($hasSecond ? 4 : ($rowH - $barH) / 2), 1) }}"
                          width="{{ round($len((float) $point['y']), 1) }}" height="{{ $barH }}"
                          class="fill-primary" />
                    <text x="{{ round($labelW + $len((float) $point['y']) + 4, 1) }}" y="{{ round($rowY + ($hasSecond ? 4 : ($rowH - $barH) / 2) + $barH - 3, 1) }}" class="fill-base-content/70 text-[10px] tabular-nums">{{ $point['y'] }}</text>
                    @if ($hasSecond && ($point['y2'] ?? null) !== null)
                        <rect x="{{ $labelW }}" y="{{ round($rowY + $barH + 8, 1) }}"
                              width="{{ round($len((float) $point['y2']), 1) }}" height="{{ $barH }}"
                              fill="url(#{{ $uid }})" class="stroke-secondary" stroke-width="1" />
                        <text x="{{ round($labelW + $len((float) $point['y2']) + 4, 1) }}" y="{{ round($rowY + $barH + 8 + $barH - 3, 1) }}" class="fill-base-content/70 text-[10px] tabular-nums">{{ $point['y2'] }}</text>
                    @endif
                </a>
            @endforeach
            <text x="{{ $width - $padR + 48 }}" y="{{ $height - 4 }}" text-anchor="end" class="fill-base-content/60 text-[10px]">{{ __('Max.') }} {{ $maxY }}</text>
        </svg>
        @if ($hasSecond)
            <p class="mt-1 flex flex-wrap gap-3 text-xs">
                <span class="inline-flex items-center gap-1">
                    <svg width="14" height="10" aria-hidden="true"><rect width="14" height="10" class="fill-primary" /></svg>
                    {{ $yLabel ?? $unit }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <svg width="14" height="10" aria-hidden="true">
                        <defs>
                            <pattern id="{{ $uid }}-lg" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                                <line x1="0" y1="0" x2="0" y2="6" class="stroke-secondary" stroke-width="2" />
                            </pattern>
                        </defs>
                        <rect width="14" height="10" fill="url(#{{ $uid }}-lg)" class="stroke-secondary" stroke-width="1" />
                    </svg>
                    {{ $y2Label }}
                </span>
            </p>
        @endif

        <div class="wd-chart-table mt-2 max-h-48 overflow-y-auto">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ $xLabel ?? __('Kategorie') }}</th>
                        <th class="text-right">{{ $yLabel ?? $unit }}</th>
                        @if ($hasSecond)<th class="text-right">{{ $y2Label }}</th>@endif
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
                        <td class="text-right tabular-nums">{{ $point['y'] }}</td>
                        @if ($hasSecond)<td class="text-right tabular-nums">{{ $point['y2'] ?? '—' }}</td>@endif
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif
</figure>
