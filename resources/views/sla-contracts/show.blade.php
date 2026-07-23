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
    /** @var \App\Models\SlaContract $contract */
    $prio = fn (string $key): string => \App\Enums\ServiceTicket\ServiceTicketPriority::tryFrom($key)?->label() ?? $key;
    $weekdays = ['', __('Mo'), __('Di'), __('Mi'), __('Do'), __('Fr'), __('Sa'), __('So')];
@endphp

@extends('layouts.app')
@section('title', $contract->code . ' — ' . $contract->label)
@section('nav-title', __('SLA-Vertrag'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ $contract->label }}</x-slot:title>
            <x-slot:subtitle>
                <span class="font-mono">{{ $contract->code }}</span> ·
                {{ $contract->customer?->name ?? __('Standard (alle Kunden)') }}
            </x-slot:subtitle>
            <x-slot:actions>
                @if ($contract->is_default)
                    <x-status-badge tone="info" size="sm">{{ __('Standard') }}</x-status-badge>
                @endif
                <x-status-badge :tone="$contract->is_active ? 'success' : 'ghost'" size="sm" outline>
                    {{ $contract->is_active ? __('Aktiv') : __('Inaktiv') }}
                </x-status-badge>
                <x-icon-btn icon="arrow_back" tone="outline" size="sm" :href="route('sla-contracts.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
            </x-slot:actions>
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
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">schedule</span>' :title="__('Keine Geschäftszeiten hinterlegt')" :message="__('Fristen laufen in Kalenderzeit.')" compact />
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
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">notifications_active</span>' :title="__('Keine Eskalationsstufen hinterlegt.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($contract->escalation_chain as $stage)
                        <li>{{ __('nach :min Min.', ['min' => $stage['after_minutes'] ?? '—']) }} → {{ $stage['notify'] ?? '—' }}</li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    {{-- Inklusivzeit-Kontingente (Rang 44) --}}
    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Inklusivzeit-Kontingente') }}</h3>
        @if (empty($quotaUsage))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">data_usage</span>' :title="__('Keine Kontingente hinterlegt.')" compact />
        @else
            <div class="space-y-3">
                @foreach ($quotaUsage as $item)
                    @php $u = $item['usage']; @endphp
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                            <span class="font-medium">{{ $item['quota']->period_kind->label() }} · {{ $u['period_key'] }}</span>
                            <span class="text-xs tabular-nums text-base-content/60">{{ $u['percentage'] }} %</span>
                        </div>
                        <progress class="progress w-full {{ $u['threshold_reached'] ? 'progress-warning' : 'progress-success' }}"
                                  value="{{ min(100, $u['percentage']) }}" max="100"></progress>
                        <div class="mt-0.5 text-xs tabular-nums text-base-content/60">
                            {{ number_format($u['consumed_minutes'] / 60, 1) }} / {{ number_format($u['included_minutes'] / 60, 1) }} {{ __('Std.') }}
                            @if ($u['over_minutes'] > 0)
                                <span class="text-error"> · {{ __(':min Min. über Kontingent', ['min' => $u['over_minutes']]) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    {{-- Vertragspflichtige Wartungstermine (Rang 43) --}}
    <x-card>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Vertragspflichtige Wartungen') }}</h3>
        @if ($maintenancePlans->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">handyman</span>' :title="__('Keine Wartungspläne mit diesem Vertrag verknüpft.')" compact />
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
                        <td class="tabular-nums">{{ $plan->next_due_on?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $plan->due_action->label() }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
