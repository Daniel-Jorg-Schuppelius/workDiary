{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pareto.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Pareto (Feature 064, P9 — Blockiergründe): absteigende Säulen +
     kumulative Prozentlinie. §Diagramm-UX erzwungen.
     $series: [['x' => Grund, 'y' => Stunden, 'url' => ?Link]] — wird hier
     absteigend sortiert; die Kumulativlinie entsteht aus derselben Reihe. --}}

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
    $points = collect($series)->sortByDesc('y')->values();
    $total = max(0.0001, (float) $points->sum('y'));
    // Ab ~9 Kategorien überlappen horizontale Labels — dann schräg (−40°)
    // mit mehr Fußraum, sonst bleibt die Achse unlesbar.
    $rotateLabels = $points->count() > 8;
    $width = 640; $pad = 36;
    $padB = $rotateLabels ? 84 : 36;
    $height = 240 + ($rotateLabels ? 48 : 0);
    $maxY = max(1, (int) ceil((float) $points->max('y')));
    $slot_ = $points->count() > 0 ? ($width - 2 * $pad) / $points->count() : 0;
    $barW = max(8, min(44, $slot_ * 0.55));
    $sy = fn(float $v): float => $height - $padB - ($v / $maxY) * ($height - $pad - $padB);
    $syPct = fn(float $pct): float => $height - $padB - ($pct / 100) * ($height - $pad - $padB);
    $running = 0.0;
    $cumPoints = $points->map(function (array $p, int $i) use (&$running, $total, $pad, $slot_, $syPct): array {
        $running += (float) $p['y'];
        return ['x' => $pad + ($i + 0.5) * $slot_, 'y' => $syPct($running / $total * 100), 'pct' => round($running / $total * 100)];
    });

    // Kontrakt für die optionale Chart.js-Verbesserung am Bildschirm (charts.js).
    $chartSpec = [
        'type' => 'bar',
        'title' => $title,
        'unit' => $unit,
        'xLabel' => $xLabel,
        'yLabel' => $yLabel ?? $unit,
        'labels' => $points->map(fn(array $p): string => (string) $p['x'])->all(),
        'urls' => $points->map(fn(array $p) => $p['url'] ?? null)->all(),
        'datasets' => [
            [
                'label' => $yLabel ?? $unit,
                'data' => $points->map(fn(array $p): float => (float) $p['y'])->all(),
                'kind' => 'bar', 'role' => 'primary',
            ],
            [
                'label' => (string) __('Kumuliert %'),
                'data' => $cumPoints->map(fn(array $p): float => (float) $p['pct'])->all(),
                'kind' => 'line', 'role' => 'compare', 'axis' => 'percent',
            ],
        ],
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
            <x-empty-state icon="align_vertical_bottom" :title="__('Noch keine Daten für dieses Diagramm.')" compact />
        </div>
    @else
        @include('components.charts._canvas', ['spec' => $chartSpec])
        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $title }}" class="wd-chart-svg mt-2 w-full">
            <line x1="{{ $pad }}" y1="{{ $height - $padB }}" x2="{{ $width - $pad }}" y2="{{ $height - $padB }}" class="stroke-base-300" stroke-width="1" />
            <line x1="{{ $pad }}" y1="{{ $pad }}" x2="{{ $pad }}" y2="{{ $height - $padB }}" class="stroke-base-300" stroke-width="1" />
            <text x="{{ $pad - 6 }}" y="{{ $pad }}" text-anchor="end" class="fill-muted text-[10px]">{{ $maxY }}</text>
            <text x="{{ $pad - 6 }}" y="{{ $height - $padB }}" text-anchor="end" class="fill-muted text-[10px]">0</text>
            @foreach ($points as $i => $point)
                @php($cx = $pad + ($i + 0.5) * $slot_)
                <a @if (!empty($point['url'])) href="{{ $point['url'] }}" @endif tabindex="0"
                   aria-label="{{ $point['x'] }}: {{ $point['y'] }} {{ $unit }} ({{ $cumPoints[$i]['pct'] }}% {{ __('kumuliert') }})">
                    <rect x="{{ round($cx - $barW / 2, 1) }}" y="{{ round($sy((float) $point['y']), 1) }}"
                          width="{{ round($barW, 1) }}" height="{{ round($height - $padB - $sy((float) $point['y']), 1) }}"
                          class="fill-primary" />
                </a>
                @if ($rotateLabels)
                    <text x="{{ round($cx, 1) }}" y="{{ $height - $padB + 12 }}" text-anchor="end"
                          transform="rotate(-40 {{ round($cx, 1) }} {{ $height - $padB + 12 }})"
                          class="fill-muted text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 18, '…') }}</text>
                @else
                    <text x="{{ round($cx, 1) }}" y="{{ $height - $padB + 12 }}" text-anchor="middle" class="fill-muted text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 10, '…') }}</text>
                @endif
            @endforeach
            <path d="{{ $cumPoints->map(fn(array $p, int $i): string => ($i === 0 ? 'M' : 'L') . round($p['x'], 1) . ' ' . round($p['y'], 1))->implode(' ') }}"
                  fill="none" class="stroke-secondary" stroke-width="2" stroke-dasharray="6 3" />
            @foreach ($cumPoints as $p)
                <circle cx="{{ round($p['x'], 1) }}" cy="{{ round($p['y'], 1) }}" r="3" class="fill-secondary" />
            @endforeach
        </svg>

        <div class="wd-chart-table mt-2 max-h-48 overflow-y-auto">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ $xLabel ?? __('Grund') }}</th>
                        <th class="text-right">{{ $yLabel ?? $unit }}</th>
                        <th class="text-right">{{ __('Kumuliert %') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($points as $i => $point)
                    <tr>
                        <td>{{ $point['x'] }}</td>
                        <td class="text-right tabular-nums">{{ $point['y'] }}</td>
                        <td class="text-right tabular-nums">{{ $cumPoints[$i]['pct'] }}%</td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif
</figure>
