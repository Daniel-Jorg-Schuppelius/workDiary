{{--
  Created on   : Thu Sep 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Portal-Abodetail (Feature 152): Stammdaten und Periodenliste mit neutralem
  Status (offen/berechnet/nicht berechnet) — keine Beträge, keine Belege.
--}}
@extends('customer.layout')

@section('title', $subscription->label)

@section('content')
<div class="space-y-4">
    <x-page-toolbar :title="$subscription->label"
                    :subtitle="$subscription->kind->label() . ($subscription->productLabel() !== null && $subscription->productLabel() !== $subscription->label ? ' · ' . $subscription->productLabel() : '')"
                    :back="route('customer.subscriptions.index')" :back-label="__('resale_portal.back')">
        <x-slot:badges>
            <x-status-badge size="sm" :tone="$subscription->status->tone()" :label="$subscription->status->label()" />
        </x-slot:badges>
    </x-page-toolbar>

    <x-card>
        <x-detail-grid layout="cells" :cols="3" small-labels>
            <x-detail-grid.row :label="__('resale_portal.field.holder')">
                {{ $subscription->holderLabel() }}
                @if ($subscription->foreignCustomer !== null)
                    <x-status-badge size="xs">{{ __('resale_portal.holder.end_customer') }}</x-status-badge>
                @endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('resale_portal.field.quantity')" class="tabular-nums">{{ $subscription->quantity }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('resale_portal.field.status')"><x-status-badge size="xs" :tone="$subscription->status->tone()" :label="$subscription->status->label()" /></x-detail-grid.row>
            <x-detail-grid.row :label="__('resale_portal.field.term')" class="tabular-nums">
                @if ($subscription->ends_on !== null)
                    {{ __('resale_portal.term.range', ['from' => $subscription->starts_on->fdate(), 'to' => $subscription->ends_on->fdate()]) }}
                @else
                    {{ __('resale_portal.term.since', ['date' => $subscription->starts_on->fdate()]) }} · {{ __('resale_portal.term.running') }}
                @endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('resale_portal.field.interval')">{{ __('resale_portal.interval.' . $subscription->interval->value) }} · {{ __('resale_portal.field.renewal') }}: {{ $subscription->renewal->label() }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('resale_portal.field.next_period')" class="tabular-nums">{{ $nextPeriod?->fdate() ?? __('resale_portal.next_period.none') }}</x-detail-grid.row>
        </x-detail-grid>
    </x-card>

    <x-card :title="__('resale_portal.periods.title')" :subtitle="__('resale_portal.periods.hint')" icon="date_range" padding="p-0">
    <x-table bare :caption="__('resale_portal.periods.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('resale_portal.field.period') }}</x-table.th>
                <x-table.th class="text-right">{{ __('resale_portal.field.quantity') }}</x-table.th>
                <x-table.th>{{ __('resale_portal.field.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($subscription->periods as $period)
            <tr class="hover">
                <td class="tabular-nums whitespace-nowrap">{{ __('resale_portal.term.range', ['from' => $period->starts_on->fdate(), 'to' => $period->ends_on->fdate()]) }}</td>
                <td class="text-right tabular-nums">{{ $period->quantity }}</td>
                <td><x-status-badge size="xs" outline :label="$periodStatusLabels[$period->status->value] ?? $period->status->value" /></td>
            </tr>
        @empty
            <x-table.empty :colspan="3" icon="date_range" :title="__('resale_portal.periods.empty')" compact />
        @endforelse
    </x-table>
    </x-card>
</div>
@endsection
