{{--
  Created on   : Mon Jul 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  SLA-Vertrag Detail (Feature 010): Fristen, Geschäftszeiten, Eskalation,
  Inklusivzeit-Kontingente (Rang 44) und vertragspflichtige Wartungen (Rang 43).
--}}

@php
    /** @var \App\Models\ServiceTicket\SlaContract $contract */
    $prio = fn (string $key): string => \App\Enums\ServiceTicket\ServiceTicketPriority::tryFrom($key)?->label() ?? $key;
    $weekdays = ['', __('Mo'), __('Di'), __('Mi'), __('Do'), __('Fr'), __('Sa'), __('So')];
@endphp

@extends('layouts.app')
@section('title', $contract->code . ' — ' . $contract->label)
@section('nav-title', __('SLA-Vertrag'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar back-route="sla-contracts.index" :back-label="__('Zurück')">
            <x-slot:title>{{ $contract->label }}</x-slot:title>
            {{-- Der Vertragscode steht in Monospace, also Markup: Standard-Slot statt
                 :subtitle — den setzt die Toolbar zusätzlich als title-Attribut. --}}
            <span class="text-xs">
                <span class="font-mono">{{ $contract->code }}</span> ·
                {{ $contract->customer?->name ?? __('Standard (alle Kunden)') }}
            </span>
            <x-slot:badges>
                @if ($contract->is_default)
                    <x-status-badge tone="info" size="sm">{{ __('Standard') }}</x-status-badge>
                @endif
                @if ($contract->is_ola)
                    <x-status-badge tone="warning" size="sm" outline>{{ __('sla.form.olaBadge', ['team' => $olaTeam?->name ?? '—']) }}</x-status-badge>
                @endif
                <x-status-badge :tone="$contract->is_active ? 'success' : 'ghost'" size="sm" outline>
                    {{ $contract->is_active ? __('Aktiv') : __('Inaktiv') }}
                </x-status-badge>
            </x-slot:badges>
            @can(\App\Enums\User\Permission::SlaContractManage->value)
                <x-slot:actions>
                    <x-icon-btn placement="bar" icon="edit" size="sm" data-entry-modal-trigger :href="route('sla-contracts.edit', $contract)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                </x-slot:actions>
            @endcan
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Fristen je Priorität --}}
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Fristen je Priorität') }}</h3>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Priorität') }}</x-table.th>
                        <x-table.th align="right">{{ __('Reaktion') }}</x-table.th>
                        <x-table.th align="right">{{ __('Lösung') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach (($contract->priority_table ?? []) as $key => $row)
                    <tr>
                        <td>{{ $prio($key) }}</td>
                        <td class="text-right tabular-nums">{{ $row['reaction_minutes'] ?? '—' }} {{ __('Min.') }}</td>
                        <td class="text-right tabular-nums">{{ $row['resolution_minutes'] ?? '—' }} {{ __('Min.') }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        {{-- Geschäftszeiten + Eskalation --}}
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Geschäftszeiten') }}</h3>
            @if (empty($contract->business_hours))
                <x-empty-state icon="schedule" :title="__('Keine Geschäftszeiten hinterlegt')" :message="__('Fristen laufen in Kalenderzeit.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($contract->business_hours as $key => $window)
                        @php $wd = (int) ($window['weekday'] ?? (is_int($key) ? $key : 0)); @endphp
                        <li class="flex justify-between">
                            <span class="text-base-content/70">{{ $weekdays[$wd] ?? ('#' . $wd) }}</span>
                            <span class="tabular-nums">{{ $window['from'] ?? '—' }}–{{ $window['to'] ?? '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <h3 class="mb-3 mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Eskalation') }}</h3>
            @if (empty($contract->escalation_chain))
                <x-empty-state icon="notifications_active" :title="__('Keine Eskalationsstufen hinterlegt.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach (array_values($contract->escalation_chain) as $i => $stage)
                        <li>{{ __('nach :min Min.', ['min' => $stage['after_minutes'] ?? '—']) }} → {{ $escalationTargets[$i] ?? '—' }}</li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    {{-- Inklusivzeit-Kontingente (Rang 44) mit Pauschale und Mehrstundensatz (Feature 010) --}}
    <x-card>
        <div class="mb-3 flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Inklusivzeit-Kontingente') }}</h3>
            @can(\App\Enums\User\Permission::SlaContractManage->value)
                <x-icon-btn icon="add" size="xs" data-entry-modal-trigger :href="route('sla-contracts.quotas.create', $contract)" show-label>{{ __('sla.quota.create') }}</x-icon-btn>
            @endcan
        </div>
        @if (empty($quotaUsage))
            <x-empty-state icon="data_usage" :title="__('Keine Kontingente hinterlegt.')" compact />
        @else
            <div class="space-y-3">
                @foreach ($quotaUsage as $item)
                    @php $u = $item['usage']; @endphp
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                            <span class="font-medium">{{ $item['quota']->period_kind->label() }} · {{ $u['period_key'] }}</span>
                            <span class="flex items-center gap-1">
                                <span class="text-xs tabular-nums text-muted">{{ $u['percentage'] }} %</span>
                                @can(\App\Enums\User\Permission::SlaContractManage->value)
                                    <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger
                                                :href="route('sla-contracts.quotas.edit', [$contract, $item['quota']])" :label="__('sla.quota.edit')" />
                                    <x-action-form :action="route('sla-contracts.quotas.destroy', [$contract, $item['quota']])" method="DELETE" :confirm="__('sla.quota.delete_confirm')">
                                        <x-icon-btn icon="delete" size="xs" tone="ghost" type="submit" :label="__('sla.quota.delete')" />
                                    </x-action-form>
                                @endcan
                            </span>
                        </div>
                        <progress class="progress w-full {{ $u['threshold_reached'] ? 'progress-warning' : 'progress-success' }}"
                                  value="{{ min(100, $u['percentage']) }}" max="100"></progress>
                        <div class="mt-0.5 text-xs tabular-nums text-muted">
                            {{ number_format($u['consumed_minutes'] / 60, 1) }} / {{ number_format($u['included_minutes'] / 60, 1) }} {{ __('Std.') }}
                            @if ($u['over_minutes'] > 0)
                                <span class="text-error"> · {{ __(':min Min. über Kontingent', ['min' => $u['over_minutes']]) }}</span>
                            @endif
                        </div>
                        @if ($u['flat_fee'] !== null || $u['overage_rate'] !== null)
                            <div class="mt-0.5 text-xs tabular-nums text-muted">
                                @if ($u['flat_fee'] !== null){{ __('sla.quota.flat_fee') }}: {{ $u['flat_fee']->format() }}@endif
                                @if ($u['flat_fee'] !== null && $u['overage_rate'] !== null) · @endif
                                @if ($u['overage_rate'] !== null){{ __('sla.quota.overage_line', ['rate' => $u['overage_rate']->format(), 'amount' => $u['overage_amount']?->format() ?? '—']) }}@endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    {{-- Vertragspflichtige Wartungstermine (Rang 43) --}}
    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Vertragspflichtige Wartungen') }}</h3>
        @if ($maintenancePlans->isEmpty())
            <x-empty-state icon="handyman" :title="__('Keine Wartungspläne mit diesem Vertrag verknüpft.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Plan') }}</x-table.th>
                        <x-table.th>{{ __('Asset') }}</x-table.th>
                        <x-table.th>{{ __('Nächste Fälligkeit') }}</x-table.th>
                        <x-table.th>{{ __('Bei Fälligkeit') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($maintenancePlans as $plan)
                    <tr>
                        <td>
                            {{ $plan->label }}
                            @if ($plan->is_contractual)
                                <x-status-badge tone="info" size="xs">{{ __('Vertragspflicht') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-base-content/70">{{ $plan->asset?->name ?? '—' }}</td>
                        <td class="tabular-nums">{{ $plan->next_due_on?->fdate() ?? '—' }}</td>
                        <td>{{ $plan->due_action->label() }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
