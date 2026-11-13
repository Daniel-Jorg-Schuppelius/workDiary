{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _cell.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zelle des Leistungsplans (Feature 199). Variablen: $cell (ServicePlanCell|null), $customerSqid, $result, $showResponsible (bool). Klick öffnet den Zellendialog. --}}
@php
    $span = $cell?->span ?? 1;
@endphp
@if ($cell === null || ! $cell->inScope())
    <td colspan="{{ $span }}" class="text-center text-muted" data-cell-state="none"><span aria-hidden="true">·</span><span class="sr-only">{{ __('service_plan.cell.out_of_scope') }}</span></td>
@elseif ($cell->hidden)
    <td colspan="{{ $span }}" class="text-center text-muted" data-cell-state="hidden"><span aria-hidden="true">—</span><span class="sr-only">{{ __('service_plan.cell.hidden') }}</span></td>
@else
    <td colspan="{{ $span }}" @class(['p-1 align-top', 'bg-error/5' => $cell->overdue]) data-cell-state="{{ $cell->state->value }}" data-cell-period="{{ $cell->period->key() }}">
        <a data-entry-modal-trigger class="flex min-w-24 flex-col gap-0.5 rounded px-1 py-0.5 hover:bg-base-200"
           href="{{ route('service-plan.cell.edit', ['customer' => $customerSqid, 'service' => $cell->service->param(), 'period' => $cell->period->key(), 'dialog' => 1]) }}"
           title="{{ $cell->orderStatus?->label() ?? $cell->state->label() }}">
            <span class="flex items-center gap-1">
                <x-status-badge size="xs" :tone="$cell->state->tone()">{{ $cell->state->label() }}</x-status-badge>
                @if ($cell->documentsReceivedOn !== null)
                    <x-icon name="inventory_2" class="text-success" :title="__('service_plan.cell.documents_received', ['date' => $cell->documentsReceivedOn->fdate()])" />
                @endif
                @if ($cell->note !== null && $cell->note !== '')
                    <x-icon name="sticky_note_2" class="text-muted" :title="$cell->note" />
                @endif
            </span>
            @if ($cell->due !== null && $cell->state->isPending())
                <span @class(['text-xs tabular-nums', 'font-semibold text-error' => $cell->overdue, 'text-muted' => ! $cell->overdue])>
                    {{ $cell->due->fdate() }}@if ($cell->overdue) <span class="sr-only">{{ __('service_plan.overdue') }}</span>@endif
                </span>
            @endif
            @if ($showResponsible && $cell->responsibleId !== null)
                <span class="truncate text-xs text-muted">{{ $result->responsibleName($cell->responsibleId) }}</span>
            @endif
        </a>
    </td>
@endif
