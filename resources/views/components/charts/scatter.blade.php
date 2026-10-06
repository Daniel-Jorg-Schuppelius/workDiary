{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : scatter.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Punktdiagramm mit Perzentil-Linien (Feature 064, P9 — Control Chart).
     §Diagramm-UX erzwungen. $series: [['x' => Label, 'y' => Zahl,
     'label' => ?Text, 'url' => ?Link]]; $percentiles: ['P50' => 24, …]. --}}

@props([
    'title',
    'unit',
    'series' => [],
    'percentiles' => [],
    'computedAt' => null,
    'note' => null,           // Datenbasis-Hinweis unter dem Titel (MVP-470)
    'xLabel' => null,
    'yLabel' => null,
])

@php
    $points = collect($series)->values();
    $range = $points->isNotEmpty() ? [$points->first()['x'], $points->last()['x']] : null;
    $width = 640; $height = 240; $pad = 36;
    $maxY = max(1, (int) ceil(max((float) $points->max('y'), (float) (collect($percentiles)->max() ?? 0))));
    $stepX = $points->count() > 1 ? ($width - 2 * $pad) / ($points->count() - 1) : 0;
    $sx = fn(int $i): float => $pad + $i * $stepX;
    $sy = fn(float $v): float => $height - $pad - ($v / $maxY) * ($height - 2 * $pad);
    $dashes = ['4 3', '7 3', '2 3'];

    // Kontrakt für die optionale Chart.js-Verbesserung am Bildschirm (charts.js).
    $chartSpec = [
        'type' => 'scatter',
        'title' => $title,
        'unit' => $unit,
        'xLabel' => $xLabel,
        'yLabel' => $yLabel ?? $unit,
        'urls' => $points->map(fn(array $p) => $p['url'] ?? null)->all(),
        'points' => $points->map(fn(array $p, int $i): array => [
            'x' => $i,
            'y' => (float) $p['y'],
            'label' => (string) ($p['label'] ?? $p['x']),
        ])->all(),
        'percentiles' => collect($percentiles)->map(fn($value, $label): array => [
            'label' => (string) $label,
            'value' => (float) $value,
        ])->values()->all(),
    ];
@endphp

<x-charts.frame :title="$title" :unit="$unit" :range="$range" :computed-at="$computedAt" :note="$note"
                :empty="$points->isEmpty()" empty-icon="scatter_plot"
                :spec="$chartSpec" :view-box="[$width, $height]">
    <line x1="{{ $pad }}" y1="{{ $height - $pad }}" x2="{{ $width - $pad }}" y2="{{ $height - $pad }}" class="stroke-base-300" stroke-width="1" />
    <line x1="{{ $pad }}" y1="{{ $pad }}" x2="{{ $pad }}" y2="{{ $height - $pad }}" class="stroke-base-300" stroke-width="1" />
    <text x="{{ $pad - 6 }}" y="{{ $pad }}" text-anchor="end" class="fill-muted text-[10px]">{{ $maxY }}</text>
    <text x="{{ $pad - 6 }}" y="{{ $height - $pad }}" text-anchor="end" class="fill-muted text-[10px]">0</text>
    @foreach ($percentiles as $label => $value)
        <line x1="{{ $pad }}" y1="{{ $sy((float) $value) }}" x2="{{ $width - $pad }}" y2="{{ $sy((float) $value) }}"
              class="stroke-base-content/40" stroke-width="1" stroke-dasharray="{{ $dashes[$loop->index % 3] }}" />
        <text x="{{ $width - $pad + 2 }}" y="{{ $sy((float) $value) + 3 }}" class="fill-muted text-[10px]">{{ $label }}</text>
    @endforeach
    @foreach ($points as $i => $point)
        <a @if (!empty($point['url'])) href="{{ $point['url'] }}" @endif tabindex="0"
           aria-label="{{ $point['label'] ?? $point['x'] }}: {{ $point['y'] }} {{ $unit }}">
            <circle cx="{{ round($sx($i), 1) }}" cy="{{ round($sy((float) $point['y']), 1) }}" r="4"
                    class="fill-primary/80 stroke-base-100" stroke-width="1" />
        </a>
    @endforeach

    <x-slot:head>
        <tr>
            <th>{{ $xLabel ?? __('Element') }}</th>
            <th class="text-right">{{ $yLabel ?? $unit }}</th>
        </tr>
    </x-slot:head>
    <x-slot:rows>
        @foreach ($points as $point)
            <tr>
                <td>
                    @if (!empty($point['url']))
                        <a href="{{ $point['url'] }}" class="link">{{ $point['label'] ?? $point['x'] }}</a>
                    @else
                        {{ $point['label'] ?? $point['x'] }}
                    @endif
                </td>
                <td class="text-right tabular-nums">{{ $point['y'] }}</td>
            </tr>
        @endforeach
    </x-slot:rows>
</x-charts.frame>
