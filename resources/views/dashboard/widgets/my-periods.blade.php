{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : my-periods.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Karte „Meine Perioden“ (Feature 199) — Daten: MyPeriodsWidget ($total, $overdue, $cells, $customers, $labels).
--}}
<x-card :title="__('service_plan.widget.title')" icon="view_week" :count="$total ?: null">
    <x-slot:actions>
        <x-button href="{{ route('service-plan.periods.index', ['responsible' => 'mine', 'year' => 'current']) }}" tone="ghost" size="xs">{{ __('service_plan.widget.open') }}</x-button>
    </x-slot:actions>

    @if ($cells === [])
        <x-empty-state compact icon="event_available" :title="__('service_plan.widget.empty_title')" :message="__('service_plan.widget.empty_message')" />
    @else
        @if ($overdue > 0)
            <p class="mb-1 text-xs font-semibold text-error">{{ trans_choice('service_plan.widget.overdue', $overdue, ['count' => $overdue]) }}</p>
        @endif
        <ul class="divide-y divide-base-300 text-sm">
            @foreach ($cells as $index => $cell)
                @php($customer = $customers->get($cell->customerId))
                <li class="flex items-start justify-between gap-3 py-2">
                    <div class="min-w-0">
                        <a class="link link-hover font-medium" data-entry-modal-trigger
                           href="{{ route('service-plan.cell.edit', ['customer' => $customer?->sqid, 'service' => $cell->service->param(), 'period' => $cell->period->key(), 'dialog' => 1]) }}">{{ $labels[$index] }} · {{ $cell->period->label() }}</a>
                        <p class="truncate text-xs text-muted">{{ $customer?->name ?? '—' }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-0.5">
                        <x-status-badge size="xs" :tone="$cell->state->tone()">{{ $cell->state->label() }}</x-status-badge>
                        @if ($cell->due !== null)
                            <span class="text-xs tabular-nums {{ $cell->overdue ? 'font-semibold text-error' : 'text-muted' }}">{{ $cell->due->fdate() }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
