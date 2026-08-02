{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sparkline.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Sparkline (MVP-464) — dokumentierter KONTRAKT-SONDERFALL zu §Diagramm-UX:
     kein <figure>, keine eigene Datentabelle, denn die Sparkline lebt IN
     einer Datentabelle (die Zeile selbst ist die gleichwertige Tabelle).
     Werte-Zugang für Screenreader über das aria-label (zuletzt/min/max).
     Leerzustand ist ein schlichtes „—". Im PDF entfällt die Sparkline —
     die Zahlenspalten der Tabelle tragen die Information.

     $values: flache Zahlenreihe in chronologischer Ordnung. --}}

@props([
    'values' => [],
    'unit' => '',
    'label' => null,
])

@php
    $nums = collect($values)->map(fn($v): float => (float) $v)->values();
    $w = 120; $h = 24; $pad = 2;
    $lo = (float) ($nums->min() ?? 0);
    $hi = (float) ($nums->max() ?? 0);
    $span = max($hi - $lo, 1e-9);
    $stepX = $nums->count() > 1 ? ($w - 2 * $pad) / ($nums->count() - 1) : 0;
    $sx = fn(int $i): float => $pad + $i * $stepX;
    $sy = fn(float $v): float => $hi === $lo ? $h / 2 : $h - $pad - (($v - $lo) / $span) * ($h - 2 * $pad);
    $path = $nums->map(fn(float $v, int $i): string => ($i === 0 ? 'M' : 'L') . round($sx($i), 1) . ' ' . round($sy($v), 1))->implode(' ');
    $num = fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
@endphp

@if ($nums->isEmpty())
    <span class="text-base-content/40">—</span>
@else
    <svg viewBox="0 0 {{ $w }} {{ $h }}" width="{{ $w }}" height="{{ $h }}" role="img" class="inline-block align-middle"
         aria-label="{{ $label ?? __('Verlauf') }}: {{ __('zuletzt') }} {{ $num((float) $nums->last()) }} {{ $unit }} ({{ __('Min') }} {{ $num($lo) }}, {{ __('Max') }} {{ $num($hi) }})">
        <path d="{{ $path }}" fill="none" class="stroke-primary" stroke-width="1.5" />
        <circle cx="{{ round($sx($nums->count() - 1), 1) }}" cy="{{ round($sy((float) $nums->last()), 1) }}" r="2" class="fill-primary" />
    </svg>
@endif
