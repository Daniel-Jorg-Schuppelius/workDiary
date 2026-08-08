{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : bar.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Säulen-Diagramm (Feature 064, P8 — §Diagramm-UX in der Komponente
     erzwungen): Kopfzeile, gleichwertige Tabelle, fokussierbare Säulen mit
     aria-label, Schraffur-Muster für die Zweitserie (nie nur Farbe),
     optionales Median-Band, Leerzustand statt Null-Achse.

     $series: Liste von ['x' => Label, 'y' => Zahl, 'y2' => ?Zahl, 'url' => ?Link]. --}}

@props([
    'title',
    'unit',
    'series' => [],
    'computedAt' => null,
    'note' => null,           // Datenbasis-Hinweis unter dem Titel (MVP-470)
    'median' => null,        // horizontale Medianlinie (Zahl)
    'xLabel' => null,
    'yLabel' => null,
    'y2Label' => null,       // Label der optionalen Zweitserie
    'compareLabel' => null,  // Label der optionalen Vergleichslinie ($point['compare'])
])

@php
    $points = collect($series)->values();
    $hasSecond = $points->contains(fn(array $p): bool => array_key_exists('y2', $p) && $p['y2'] !== null);
    // Optionale Vergleichslinie (z. B. Vorjahr) je Kategorie — als überlagerte Linie.
    $hasCompare = $points->contains(fn(array $p): bool => array_key_exists('compare', $p) && $p['compare'] !== null);
    // Ab ~9 Kategorien überlappen horizontale Labels — dann schräg (−40°) mit mehr Fußraum.
    $rotateLabels = $points->count() > 8;
    $width = 640; $pad = 36;
    $padB = $rotateLabels ? 84 : 36;
    $height = 240 + ($rotateLabels ? 48 : 0);
    $maxY = max(1, (int) ceil(max((float) $points->max('y'), (float) ($hasSecond ? $points->max('y2') : 0), (float) ($hasCompare ? $points->max('compare') : 0), (float) ($median ?? 0))));
    $slot_ = $points->count() > 0 ? ($width - 2 * $pad) / $points->count() : 0;
    $barW = max(6, min(38, $slot_ * ($hasSecond ? 0.32 : 0.55)));
    $sy = fn(float $v): float => $height - $padB - ($v / $maxY) * ($height - $pad - $padB);
    $uid = 'hatch-' . uniqid();

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
    if ($hasCompare) {
        $chartDatasets[] = [
            'label' => $compareLabel ?? __('Vergleich'),
            'data' => $points->map(fn(array $p) => ($p['compare'] ?? null) === null ? null : (float) $p['compare'])->all(),
            'kind' => 'line', 'role' => 'compare', 'dashed' => true,
        ];
    }
    $chartSpec = [
        'type' => 'bar',
        'title' => $title,
        'unit' => $unit,
        'xLabel' => $xLabel,
        'yLabel' => $yLabel ?? $unit,
        'median' => $median !== null ? (float) $median : null,
        'labels' => $points->map(fn(array $p): string => (string) $p['x'])->all(),
        'urls' => $points->map(fn(array $p) => $p['url'] ?? null)->all(),
        'datasets' => $chartDatasets,
    ];
@endphp

<figure class="wd-chart rounded-box border border-base-300 bg-base-100 p-3">
    <figcaption>
        <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ $title }}</span>
        <span class="ml-2 text-xs text-base-content/60">
            {{ $unit }}
            @if ($points->isNotEmpty()) · {{ $points->first()['x'] }} – {{ $points->last()['x'] }} @endif
            @if ($computedAt) · {{ __('Stand:') }} {{ \Illuminate\Support\Carbon::parse($computedAt)->isoFormat('L LT') }} @endif
        </span>
    </figcaption>

    @if ($note)
        <p class="mt-1 text-xs text-base-content/50">{{ $note }}</p>
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
            <line x1="{{ $pad }}" y1="{{ $height - $padB }}" x2="{{ $width - $pad }}" y2="{{ $height - $padB }}" class="stroke-base-300" stroke-width="1" />
            <line x1="{{ $pad }}" y1="{{ $pad }}" x2="{{ $pad }}" y2="{{ $height - $padB }}" class="stroke-base-300" stroke-width="1" />
            <text x="{{ $pad - 6 }}" y="{{ $pad }}" text-anchor="end" class="fill-base-content/60 text-[10px]">{{ $maxY }}</text>
            <text x="{{ $pad - 6 }}" y="{{ $height - $padB }}" text-anchor="end" class="fill-base-content/60 text-[10px]">0</text>
            @if ($median !== null)
                <line x1="{{ $pad }}" y1="{{ $sy((float) $median) }}" x2="{{ $width - $pad }}" y2="{{ $sy((float) $median) }}"
                      class="stroke-base-content/40" stroke-width="1" stroke-dasharray="2 3" />
                <text x="{{ $width - $pad + 2 }}" y="{{ $sy((float) $median) + 3 }}" class="fill-base-content/60 text-[10px]">{{ __('Median') }}</text>
            @endif
            @foreach ($points as $i => $point)
                @php
                    $cx = $pad + ($i + 0.5) * $slot_;
                    $ariaExtra = '';
                    if ($hasSecond && ($point['y2'] ?? null) !== null) {
                        $ariaExtra .= ', ' . ($y2Label ?? '') . ': ' . $point['y2'];
                    }
                    if ($hasCompare && ($point['compare'] ?? null) !== null) {
                        $ariaExtra .= ', ' . ($compareLabel ?? '') . ': ' . $point['compare'];
                    }
                @endphp
                <a @if (!empty($point['url'])) href="{{ $point['url'] }}" @endif tabindex="0"
                   aria-label="{{ $point['x'] }}: {{ $point['y'] }} {{ $unit }}{{ $ariaExtra }}">
                    <rect x="{{ round($cx - ($hasSecond ? $barW : $barW / 2), 1) }}" y="{{ round($sy((float) $point['y']), 1) }}"
                          width="{{ round($barW, 1) }}" height="{{ round($height - $padB - $sy((float) $point['y']), 1) }}"
                          class="fill-primary" />
                    @if ($hasSecond && ($point['y2'] ?? null) !== null)
                        <rect x="{{ round($cx + 1, 1) }}" y="{{ round($sy((float) $point['y2']), 1) }}"
                              width="{{ round($barW, 1) }}" height="{{ round($height - $padB - $sy((float) $point['y2']), 1) }}"
                              fill="url(#{{ $uid }})" class="stroke-secondary" stroke-width="1" />
                    @endif
                </a>
                @if ($rotateLabels)
                    <text x="{{ round($cx, 1) }}" y="{{ $height - $padB + 12 }}" text-anchor="end"
                          transform="rotate(-40 {{ round($cx, 1) }} {{ $height - $padB + 12 }})"
                          class="fill-base-content/60 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 18, '…') }}</text>
                @else
                    <text x="{{ round($cx, 1) }}" y="{{ $height - $padB + 12 }}" text-anchor="middle" class="fill-base-content/60 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 10, '…') }}</text>
                @endif
            @endforeach
            @if ($hasCompare)
                @php
                    $cmp = $points->map(fn(array $p, int $i): ?array => ($p['compare'] ?? null) === null ? null : [
                        'x' => $pad + ($i + 0.5) * $slot_,
                        'y' => $sy((float) $p['compare']),
                    ])->filter()->values();
                @endphp
                @if ($cmp->count() > 1)
                    <polyline fill="none" class="stroke-accent" stroke-width="2" stroke-dasharray="4 3"
                              points="{{ $cmp->map(fn(array $c): string => round($c['x'], 1) . ',' . round($c['y'], 1))->implode(' ') }}" />
                @endif
                @foreach ($cmp as $c)
                    <circle cx="{{ round($c['x'], 1) }}" cy="{{ round($c['y'], 1) }}" r="2.5" class="fill-accent" />
                @endforeach
            @endif
        </svg>

        @if ($hasCompare)
            <p class="mt-1 text-xs text-base-content/60">
                <span class="inline-flex items-center gap-1">
                    <svg width="16" height="8" aria-hidden="true"><line x1="0" y1="4" x2="16" y2="4" class="stroke-accent" stroke-width="2" stroke-dasharray="4 3" /></svg>
                    {{ $compareLabel ?? __('Vergleich') }}
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
                        @if ($hasCompare)<th class="text-right">{{ $compareLabel ?? __('Vergleich') }}</th>@endif
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
                        @if ($hasCompare)<td class="text-right tabular-nums">{{ $point['compare'] ?? '—' }}</td>@endif
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif
</figure>
