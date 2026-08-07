{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : waterfall.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Waterfall/Brücke (MVP-464, §Diagramm-UX erzwungen): Startbestand →
     +/−-Beiträge → Endbestand. Abnahmen zusätzlich zur Farbe schraffiert
     (Farbe nie alleiniger Träger), gestrichelte Konnektoren auf dem
     kumulierten Stand, Bestandssäulen neutral.

     $series: [['x' => Label, 'y' => Δ-Wert, 'url' => ?Drilldown]];
     $startValue/$startLabel: Startbestand (Säule), $endLabel: Endsäule. --}}

@props([
    'title',
    'unit',
    'series' => [],
    'startValue' => 0,
    'startLabel' => null,
    'endLabel' => null,
    'computedAt' => null,
    'note' => null,           // Datenbasis-Hinweis unter dem Titel (MVP-470)
    'xLabel' => null,
    'yLabel' => null,
])

@php
    $deltas = collect($series)->values();
    $startLabel ??= __('Anfangsbestand');
    $endLabel ??= __('Endbestand');
    $cols = collect();
    $running = (float) $startValue;
    $cols->push(['x' => $startLabel, 'type' => 'total', 'from' => 0.0, 'to' => $running, 'delta' => null, 'url' => null]);
    foreach ($deltas as $d) {
        $from = $running;
        $running += (float) $d['y'];
        $cols->push(['x' => $d['x'], 'type' => 'delta', 'from' => $from, 'to' => $running, 'delta' => (float) $d['y'], 'url' => $d['url'] ?? null]);
    }
    $cols->push(['x' => $endLabel, 'type' => 'total', 'from' => 0.0, 'to' => $running, 'delta' => null, 'url' => null]);

    $rotateLabels = $cols->count() > 8;
    $width = 640; $pad = 36;
    $padB = $rotateLabels ? 84 : 36;
    $height = 240 + ($rotateLabels ? 48 : 0);
    $lo = min(0.0, (float) $cols->min('from'), (float) $cols->min('to'));
    $hi = max(1.0, (float) $cols->max('from'), (float) $cols->max('to'));
    $sy = fn(float $v): float => $height - $padB - (($v - $lo) / ($hi - $lo)) * ($height - $pad - $padB);
    $slot_ = $cols->count() > 0 ? ($width - 2 * $pad) / $cols->count() : 0;
    $barW = max(8, min(44, $slot_ * 0.6));
    $uid = 'hatch-wf-' . uniqid();
    $fmt = fn(float $v): string => ($v > 0 ? '+' : ($v < 0 ? '−' : '±')) . rtrim(rtrim(number_format(abs($v), 2, '.', ''), '0'), '.');
@endphp

<figure class="wd-chart rounded-box border border-base-300 bg-base-100 p-3">
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

    @if ($deltas->isEmpty())
        <div class="wd-chart-empty">
            <x-empty-state icon="waterfall_chart" :title="__('Noch keine Daten für dieses Diagramm.')" compact />
        </div>
    @else
        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $title }}" class="mt-2 w-full">
            <defs>
                <pattern id="{{ $uid }}" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                    <line x1="0" y1="0" x2="0" y2="6" class="stroke-secondary" stroke-width="2" />
                </pattern>
            </defs>
            <line x1="{{ $pad }}" y1="{{ round($sy(0), 1) }}" x2="{{ $width - $pad }}" y2="{{ round($sy(0), 1) }}" class="stroke-base-300" stroke-width="1" />
            <line x1="{{ $pad }}" y1="{{ $pad }}" x2="{{ $pad }}" y2="{{ $height - $padB }}" class="stroke-base-300" stroke-width="1" />
            <text x="{{ $pad - 6 }}" y="{{ $pad }}" text-anchor="end" class="fill-base-content/60 text-[10px]">{{ rtrim(rtrim(number_format($hi, 2, '.', ''), '0'), '.') }}</text>
            <text x="{{ $pad - 6 }}" y="{{ round($sy(0), 1) }}" text-anchor="end" class="fill-base-content/60 text-[10px]">0</text>
            @foreach ($cols as $i => $col)
                @php
                    $cx = $pad + ($i + 0.5) * $slot_;
                    $x0 = round($cx - $barW / 2, 1);
                    $yTop = round(min($sy($col['from']), $sy($col['to'])), 1);
                    $h = round(max(2, abs($sy($col['from']) - $sy($col['to']))), 1);
                    $isDown = $col['type'] === 'delta' && ($col['delta'] ?? 0) < 0;
                @endphp
                <a @if (!empty($col['url'])) href="{{ $col['url'] }}" @endif tabindex="0"
                   aria-label="@if ($col['type'] === 'total'){{ $col['x'] }}: {{ rtrim(rtrim(number_format($col['to'], 2, '.', ''), '0'), '.') }} {{ $unit }}@else{{ $col['x'] }}: {{ $fmt((float) $col['delta']) }} {{ $unit }}, {{ __('Stand:') }} {{ rtrim(rtrim(number_format($col['to'], 2, '.', ''), '0'), '.') }}@endif">
                    @if ($col['type'] === 'total')
                        <rect x="{{ $x0 }}" y="{{ $yTop }}" width="{{ round($barW, 1) }}" height="{{ $h }}" class="fill-base-content/40" />
                    @elseif ($isDown)
                        <rect x="{{ $x0 }}" y="{{ $yTop }}" width="{{ round($barW, 1) }}" height="{{ $h }}" fill="url(#{{ $uid }})" class="stroke-secondary" stroke-width="1" />
                    @else
                        <rect x="{{ $x0 }}" y="{{ $yTop }}" width="{{ round($barW, 1) }}" height="{{ $h }}" class="fill-primary" />
                    @endif
                    <text x="{{ round($cx, 1) }}" y="{{ $yTop - 4 }}" text-anchor="middle" class="fill-base-content/70 text-[10px] tabular-nums">{{ $col['type'] === 'total' ? rtrim(rtrim(number_format($col['to'], 2, '.', ''), '0'), '.') : $fmt((float) $col['delta']) }}</text>
                </a>
                @if ($i < $cols->count() - 1)
                    <line x1="{{ round($cx + $barW / 2, 1) }}" y1="{{ round($sy($col['to']), 1) }}"
                          x2="{{ round($pad + ($i + 1.5) * $slot_ - $barW / 2, 1) }}" y2="{{ round($sy($col['to']), 1) }}"
                          class="stroke-base-content/30" stroke-width="1" stroke-dasharray="4 3" />
                @endif
                @if ($rotateLabels)
                    <text x="{{ round($cx, 1) }}" y="{{ $height - $padB + 12 }}" text-anchor="end"
                          transform="rotate(-40 {{ round($cx, 1) }} {{ $height - $padB + 12 }})"
                          class="fill-base-content/60 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $col['x'], 18, '…') }}</text>
                @else
                    <text x="{{ round($cx, 1) }}" y="{{ $height - $padB + 12 }}" text-anchor="middle" class="fill-base-content/60 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $col['x'], 10, '…') }}</text>
                @endif
            @endforeach
        </svg>

        <p class="mt-1 flex flex-wrap gap-3 text-xs">
            <span class="inline-flex items-center gap-1">
                <svg width="14" height="10" aria-hidden="true"><rect width="14" height="10" class="fill-primary" /></svg>
                {{ __('Zunahme') }}
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
                {{ __('Abnahme') }}
            </span>
            <span class="inline-flex items-center gap-1">
                <svg width="14" height="10" aria-hidden="true"><rect width="14" height="10" class="fill-base-content/40" /></svg>
                {{ __('Bestand') }}
            </span>
        </p>

        <div class="wd-chart-table mt-2 max-h-48 overflow-y-auto">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ $xLabel ?? __('Schritt') }}</th>
                        <th class="text-right">{{ $yLabel ?? __('Veränderung') }}</th>
                        <th class="text-right">{{ __('Stand') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($cols as $col)
                    <tr>
                        <td>
                            @if (!empty($col['url']))
                                <a href="{{ $col['url'] }}" class="link">{{ $col['x'] }}</a>
                            @else
                                {{ $col['x'] }}
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $col['delta'] !== null ? $fmt((float) $col['delta']) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ rtrim(rtrim(number_format($col['to'], 2, '.', ''), '0'), '.') }}</td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif
</figure>
