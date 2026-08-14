{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _balance.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Tagesabschluss-Sektion „Bilanz" (MVP-015 §2.5), inkl. der zusammengelegten
  Pausen (Ist/Soll + Pflichtpausen-Warnung). Gemeinsamer Partial für
  Tagesabschluss- und „Heute"-Seite.

  $compact (optional, Default false): blendet die Felder aus, die auf „Heute"
  bereits als KPI-Kacheln stehen (Soll=target, Anwesenheit=net, Erfasst=booked,
  Unverteilt≈diff) — Doppelung vermeiden. Die Tagesabschluss-Seite (ohne
  Kacheln) nutzt die volle Bilanz.

  Erwartet aus dem Host-Scope: $aggregates, $issues, $validator, $fmtMin.
--}}
@php
    $compact = $compact ?? false;
    $breakIssue = collect($issues)->firstWhere('code', \App\Services\TimeApproval\DayClosureValidator::CHECK_BREAK_REQUIRED);
@endphp
<x-card as="section">
    <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
        <span class="material-symbols-outlined" aria-hidden="true">analytics</span>
        {{ __('day-close.section.balance') }}
    </h2>
    {{-- Kompakt zeigt genau 5 Felder → einzeilig (md:grid-cols-5); die volle
         Bilanz (9 Felder) bleibt im 4-Spalten-Raster. --}}
    <div class="grid grid-cols-2 gap-3 text-sm {{ $compact ? 'sm:grid-cols-3 md:grid-cols-5' : 'md:grid-cols-4' }}">
        @unless ($compact)
            <div>
                <div class="text-xs opacity-70">{{ __('day-close.field.target') }}</div>
                <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['target']) }}</div>
            </div>
        @endunless
        <div>
            <div class="text-xs opacity-70">{{ __('day-close.field.gross') }}</div>
            <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['gross']) }}</div>
        </div>
        <div>
            <div class="text-xs opacity-70">{{ __('day-close.field.recorded_break') }}</div>
            <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['breaks']) }}</div>
        </div>
        <div>
            <div class="text-xs opacity-70">{{ __('day-close.field.required_break') }}</div>
            <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['required_break']) }}</div>
        </div>
        @unless ($compact)
            <div>
                <div class="text-xs opacity-70">{{ __('day-close.field.net') }}</div>
                <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['net']) }}</div>
            </div>
            <div>
                <div class="text-xs opacity-70">{{ __('day-close.field.booked') }}</div>
                <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['booked']) }}</div>
            </div>
            <div>
                <div class="text-xs opacity-70">{{ __('day-close.field.diff') }}</div>
                <div @class(['font-medium tabular-nums', 'text-warning' => abs($aggregates['diff']) > 5])>{{ $fmtMin($aggregates['diff']) }}</div>
            </div>
        @endunless
        <div>
            <div class="text-xs opacity-70">{{ __('day-close.field.day_balance') }}</div>
            <div @class(['font-medium tabular-nums', 'text-warning' => abs($aggregates['day_balance']) > 120])>{{ $fmtMin($aggregates['day_balance']) }}</div>
        </div>
        <div>
            <div class="text-xs opacity-70">{{ __('day-close.field.month_balance') }}</div>
            <div class="font-medium tabular-nums">{{ $fmtMin($aggregates['month_balance']) }}</div>
        </div>
    </div>

    {{-- Pflichtpausen-Warnung (⛔), aus der ehemals eigenen Pausen-Sektion. --}}
    @if ($breakIssue)
        <div role="alert" class="alert alert-error mt-3">
            <span class="material-symbols-outlined" aria-hidden="true">block</span>
            <span>{{ $validator->messageFor($breakIssue) }}</span>
        </div>
    @endif
</x-card>
