{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _cockpit_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Leistungsplan am Kunden-Cockpit (Feature 199). Variablen: $customer, $cells (ServicePlanCell, offene zuerst), $labels (Leistung → Bezeichnung), $pending. --}}
<x-card as="section" id="service-plan" :title="__('service_plan.title')" icon="calendar_view_month" :count="$pending ?: null" padding="p-0">
    <ul class="divide-y divide-base-300">
        @foreach ($cells as $cell)
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
                <a class="link link-hover min-w-0 truncate font-medium" data-entry-modal-trigger
                   href="{{ route('service-plan.cell.edit', ['customer' => $customer->sqid, 'service' => $cell->service->param(), 'period' => $cell->period->key(), 'dialog' => 1]) }}">{{ $labels[$cell->service->key()] ?? '' }} · {{ $cell->period->label() }}</a>
                <div class="flex items-center gap-2">
                    @if ($cell->due !== null && $cell->state->isPending())
                        <span @class(['text-xs tabular-nums', 'font-semibold text-error' => $cell->overdue, 'text-muted' => ! $cell->overdue])>{{ $cell->due->fdate() }}</span>
                    @endif
                    <x-status-badge size="xs" :tone="$cell->state->tone()">{{ $cell->state->label() }}</x-status-badge>
                </div>
            </li>
        @endforeach
    </ul>
    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-base-300 px-4 py-2 text-xs">
        <a class="link link-hover" href="{{ route('service-plan.index', ['customer' => $customer->sqid]) }}">{{ __('service_plan.view.year') }}</a>
        <a class="link link-hover" href="{{ route('service-plan.periods.index', ['customer' => $customer->sqid]) }}">{{ __('service_plan.view.periods') }}</a>
    </div>
</x-card>
