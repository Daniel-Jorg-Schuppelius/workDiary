{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : finance-movements.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Finanzbewegungen“ (MVP-1617) — Daten: FinanceMovementsWidget
  ($rows: list<{source, summary}>, $latest: list<{source, movement}>, $days).
  Die Zählkachel führt auf die Liste der Quelle, gefiltert auf das Gezählte.
--}}
<x-card :title="__('dashboard.widget.finance_movements.label')" icon="account_balance_wallet">
    @if ($rows === [])
        <x-empty-state compact icon="account_balance_wallet" :title="__('dashboard.widget.finance_movements.empty')" />
    @else
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($rows as $row)
                @if ($row['summary']->unassigned !== null)
                    <x-kpi-tile :label="__('dashboard.widget.finance_movements.unassigned', ['source' => $row['source']->label()])"
                                :value="$row['summary']->unassigned"
                                :tone="$row['summary']->unassigned > 0 ? 'warning' : 'success'"
                                :href="$row['summary']->unassignedUrl ?? $row['summary']->url" />
                @endif
            @endforeach
        </div>

        <p class="mt-3 text-xs uppercase tracking-wider text-muted">{{ __('dashboard.widget.finance_movements.latest', ['days' => $days]) }}</p>
        @if ($latest === [])
            <x-empty-state compact icon="history" :title="__('dashboard.widget.finance_movements.none_latest')" />
        @else
            <ul class="divide-y divide-base-300 text-sm">
                @foreach ($latest as $entry)
                    @php($movement = $entry['movement'])
                    <li class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                        <div class="min-w-0">
                            <a href="{{ $entry['url'] }}" class="link link-hover font-medium">{{ $movement->title }}</a>
                            <span class="text-xs text-muted">· {{ $entry['source']->label() }} · {{ $movement->bookedOn->fdate() }}@if ($movement->detail !== null) · {{ $movement->detail }}@endif</span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($movement->assigned === false)
                                <x-status-badge size="xs" tone="warning">{{ __('dashboard.widget.finance_movements.open') }}</x-status-badge>
                            @endif
                            <span class="tabular-nums {{ $movement->amount->isNegative() ? 'text-error' : 'text-success' }}">{{ $movement->amount->format() }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</x-card>
