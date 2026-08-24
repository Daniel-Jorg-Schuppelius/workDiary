{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : area-stack.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Gestapelte Flächen (Feature 064, P9 — CFD). §Diagramm-UX erzwungen:
     Kopf, gleichwertige Tabelle, Legende mit Muster (nicht nur Farbe),
     Leerzustand. $series: [['x' => Datum, 'a' => n, 'b' => n, …]] mit
     $bands: geordnete Liste ['key' => 'a', 'label' => …]. --}}

@props([
    'title',
    'unit',
    'series' => [],
    'bands' => [],           // unten → oben: [['key' => 'open', 'label' => 'Offen'], …]
    'computedAt' => null,
    'note' => null,           // Datenbasis-Hinweis unter dem Titel (MVP-470)
    'xLabel' => null,
])

@php
    $points = collect($series)->values();
    $width = 640; $height = 240; $pad = 36;
    $totals = $points->map(fn(array $p): float => (float) collect($bands)->sum(fn(array $b) => (float) ($p[$b['key']] ?? 0)));
    $maxY = max(1, (int) ceil((float) $totals->max()));
    $stepX = $points->count() > 1 ? ($width - 2 * $pad) / ($points->count() - 1) : 0;
    $sx = fn(int $i): float => $pad + $i * $stepX;
    $sy = fn(float $v): float => $height - $pad - ($v / $maxY) * ($height - 2 * $pad);
    $fills = ['fill-primary/70', 'fill-secondary/60', 'fill-accent/50', 'fill-info/50'];
    $uid = 'as-' . uniqid();

    // Kumulierte Pfade je Band (unten beginnend).
    $paths = [];
    $cumulative = array_fill(0, $points->count(), 0.0);
    foreach ($bands as $bandIndex => $band) {
        $lower = $cumulative;
        foreach ($points as $i => $p) {
            $cumulative[$i] += (float) ($p[$band['key']] ?? 0);
        }
        $top = collect($cumulative)->map(fn(float $v, int $i): string => round($sx($i), 1) . ' ' . round($sy($v), 1));
        $bottom = collect($lower)->reverse()->map(fn(float $v, int $i): string => round($sx($i), 1) . ' ' . round($sy($v), 1));
        $paths[$bandIndex] = 'M' . $top->implode(' L') . ' L' . $bottom->implode(' L') . ' Z';
    }
@endphp

<figure class="wd-chart rounded-box border border-base-300 bg-base-100 p-3">
    <figcaption>
        <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ $title }}</span>
        <span class="ml-2 text-xs text-muted">
            {{ $unit }}
            @if ($points->isNotEmpty()) · {{ $points->first()['x'] }} – {{ $points->last()['x'] }} @endif
            @if ($computedAt) · {{ __('Stand:') }} {{ \Illuminate\Support\Carbon::parse($computedAt)->isoFormat('L LT') }} @endif
        </span>
    </figcaption>

    @if ($note)
        <p class="mt-1 text-xs text-muted">{{ $note }}</p>
    @endif

    @if ($points->count() < 2)
        <div class="wd-chart-empty">
            <x-empty-state icon="area_chart" :title="__('Noch keine Daten für dieses Diagramm.')" compact />
        </div>
    @else
        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $title }}" class="mt-2 w-full">
            <line x1="{{ $pad }}" y1="{{ $height - $pad }}" x2="{{ $width - $pad }}" y2="{{ $height - $pad }}" class="stroke-base-300" stroke-width="1" />
            <line x1="{{ $pad }}" y1="{{ $pad }}" x2="{{ $pad }}" y2="{{ $height - $pad }}" class="stroke-base-300" stroke-width="1" />
            <text x="{{ $pad - 6 }}" y="{{ $pad }}" text-anchor="end" class="fill-base-content/60 text-[10px]">{{ $maxY }}</text>
            <text x="{{ $pad - 6 }}" y="{{ $height - $pad }}" text-anchor="end" class="fill-base-content/60 text-[10px]">0</text>
            @foreach ($bands as $bandIndex => $band)
                <path d="{{ $paths[$bandIndex] }}" class="{{ $fills[$bandIndex % count($fills)] }} stroke-base-100" stroke-width="0.5" />
            @endforeach
        </svg>
        <p class="mt-1 flex flex-wrap gap-3 text-xs">
            @foreach ($bands as $bandIndex => $band)
                <span class="inline-flex items-center gap-1">
                    <svg width="14" height="10" aria-hidden="true"><rect width="14" height="10" class="{{ $fills[$bandIndex % count($fills)] }}" /></svg>
                    {{ $bandIndex + 1 }}. {{ $band['label'] }}
                </span>
            @endforeach
        </p>

        <div class="wd-chart-table mt-2 max-h-48 overflow-y-auto">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ $xLabel ?? __('Datum') }}</th>
                        @foreach ($bands as $band)<th class="text-right">{{ $band['label'] }}</th>@endforeach
                    </tr>
                </x-slot:head>
                @foreach ($points as $point)
                    <tr>
                        <td>{{ $point['x'] }}</td>
                        @foreach ($bands as $band)<td class="text-right tabular-nums">{{ $point[$band['key']] ?? 0 }}</td>@endforeach
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif
</figure>
