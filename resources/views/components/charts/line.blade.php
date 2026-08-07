{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : line.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Linien-Diagramm (Feature 064, P8 — Vorgabe §Diagramm-UX, hier ERZWUNGEN,
     nicht je Aufrufstelle): Titel/Einheit/Zeitraum/Datenstand im Kopf,
     gleichwertige Tabelle direkt darunter (dasselbe Datenarray),
     fokussierbare Datenpunkte mit aria-label, Marker zusätzlich zur Farbe,
     optionale gestrichelte Ideallinie, Leerzustand statt Null-Linie.

     $series: Liste von ['x' => Label, 'y' => Zahl, 'url' => ?Drilldown]. --}}

@props([
    'title',
    'unit',
    'series' => [],          // [['x' => '2026-07-01', 'y' => 8, 'url' => null], …]
    'computedAt' => null,    // Datenstand (Carbon|string)
    'note' => null,           // Datenbasis-Hinweis unter dem Titel (MVP-470)
    'ideal' => false,        // gestrichelte Ideallinie vom ersten Wert auf 0
    'xLabel' => null,
    'yLabel' => null,
])

@php
    $points = collect($series)->values();
    // Ab ~9 Punkten überlappen waagerechte X-Labels — dann schräg (−40°) mit mehr Fußraum.
    $rotateLabels = $points->count() > 8;
    $width = 640; $pad = 36;
    $padB = $rotateLabels ? 84 : 44;
    $height = 240 + ($rotateLabels ? 48 : 0);
    $maxY = max(1, (int) ceil((float) $points->max('y')));
    $stepX = $points->count() > 1 ? ($width - 2 * $pad) / ($points->count() - 1) : 0;
    $sx = fn(int $i): float => $pad + $i * $stepX;
    $sy = fn(float $v): float => $height - $padB - ($v / $maxY) * ($height - $pad - $padB);
    $path = $points->map(fn(array $p, int $i): string => ($i === 0 ? 'M' : 'L') . round($sx($i), 1) . ' ' . round($sy((float) $p['y']), 1))->implode(' ');
    // X-Achsenbeschriftung ausdünnen: höchstens ~10 Labels, letzter Punkt immer.
    $labelEvery = max(1, (int) ceil($points->count() / 10));
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
            <x-empty-state icon="show_chart" :title="__('Noch keine Daten für dieses Diagramm.')" compact />
        </div>
    @else
        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $title }}" class="mt-2 w-full">
            <line x1="{{ $pad }}" y1="{{ $height - $padB }}" x2="{{ $width - $pad }}" y2="{{ $height - $padB }}" class="stroke-base-300" stroke-width="1" />
            <line x1="{{ $pad }}" y1="{{ $pad }}" x2="{{ $pad }}" y2="{{ $height - $padB }}" class="stroke-base-300" stroke-width="1" />
            <text x="{{ $pad - 6 }}" y="{{ $pad }}" text-anchor="end" class="fill-base-content/60 text-[10px]">{{ $maxY }}</text>
            <text x="{{ $pad - 6 }}" y="{{ $height - $padB }}" text-anchor="end" class="fill-base-content/60 text-[10px]">0</text>
            @if ($ideal && $points->count() > 1)
                <line x1="{{ $sx(0) }}" y1="{{ $sy((float) $points->first()['y']) }}"
                      x2="{{ $sx($points->count() - 1) }}" y2="{{ $sy(0) }}"
                      class="stroke-base-content/30" stroke-width="1" stroke-dasharray="5 4" />
            @endif
            <path d="{{ $path }}" fill="none" class="stroke-primary" stroke-width="2" />
            @foreach ($points as $i => $point)
                @if ($i % $labelEvery === 0 || $i === $points->count() - 1)
                    @if ($rotateLabels)
                        <text x="{{ round($sx($i), 1) }}" y="{{ $height - $padB + 12 }}" text-anchor="end"
                              transform="rotate(-40 {{ round($sx($i), 1) }} {{ $height - $padB + 12 }})"
                              class="fill-base-content/60 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 18, '…') }}</text>
                    @else
                        <text x="{{ round($sx($i), 1) }}" y="{{ $height - $padB + 14 }}" text-anchor="middle" class="fill-base-content/60 text-[10px]">{{ \Illuminate\Support\Str::limit((string) $point['x'], 10, '…') }}</text>
                    @endif
                @endif
            @endforeach
            @foreach ($points as $i => $point)
                <a @if (!empty($point['url'])) href="{{ $point['url'] }}" @endif tabindex="0"
                   aria-label="{{ $point['x'] }}: {{ $point['y'] }} {{ $unit }}">
                    <circle cx="{{ round($sx($i), 1) }}" cy="{{ round($sy((float) $point['y']), 1) }}" r="4"
                            class="fill-primary stroke-base-100" stroke-width="1.5" />
                </a>
            @endforeach
        </svg>

        {{-- Gleichwertige Tabelle (Pflicht) — dasselbe Datenarray. --}}
        <div class="wd-chart-table mt-2 max-h-48 overflow-y-auto">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ $xLabel ?? __('Zeitpunkt') }}</th>
                        <th class="text-right">{{ $yLabel ?? $unit }}</th>
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
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif
</figure>
