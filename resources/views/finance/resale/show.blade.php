{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abo-Detail (Feature 152, MVP-758): Stammdaten, Halter, Preise und die
  geplanten Abrechnungsperioden mit Status. Rechnungsbezüge und
  Entscheidungen je Periode kommen mit MVP-761.
--}}
@extends('layouts.app')
@section('title', $subscription->label)
@section('nav-title', __('resale.title.menu'))

@php
    $billedTo = $subscription->billedTo();
    $openCount = $subscription->openPeriodCount();
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
@endphp

@section('content')
    <x-index-page :title="$subscription->label" :subtitle="$subscription->kind->label() . ' · ' . $subscription->provider->label()">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="edit" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.edit', $subscription->sqid)"
                            show-label>{{ __('resale.action.edit') }}</x-icon-btn>
                <form method="POST" action="{{ route('finance.resale.destroy', $subscription->sqid) }}" data-confirm="{{ __('resale.confirm.delete') }}">
                    @csrf
                    @method('DELETE')
                    <x-icon-btn icon="delete" tone="ghost" size="sm" type="submit" show-label>{{ __('resale.action.delete') }}</x-icon-btn>
                </form>
            @endif
            @if ($canManage && $subscription->hasHolder() && ! $subscription->is_own_holding)
                <form method="POST" action="{{ route('finance.resale.periods.propose') }}">
                    @csrf
                    <x-icon-btn icon="auto_awesome" tone="ghost" size="sm" type="submit" show-label>{{ __('resale.link.action.propose') }}</x-icon-btn>
                </form>
            @endif
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.resale.index')" show-label>{{ __('resale.action.back') }}</x-icon-btn>
        </x-slot:actions>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <x-card :title="__('resale.section.holder')">
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt class="text-muted">{{ __('resale.field.holder') }}</dt>
                    <dd>
                        @if (! $subscription->hasHolder())
                            <x-status-badge size="xs" tone="warning" :label="__('resale.holder.unassigned')" />
                        @elseif ($subscription->is_own_holding)
                            {{ __('resale.holder.own') }}
                        @elseif ($subscription->foreignCustomer !== null)
                            {{ $subscription->foreignCustomer->name }}
                            <span class="block text-xs text-muted">{{ __('resale.holder.via', ['partner' => $subscription->foreignCustomer->customer?->name]) }}</span>
                        @else
                            <a href="{{ route('customers.show', $subscription->customer) }}" class="link link-hover">{{ $subscription->customer?->name }}</a>
                        @endif
                    </dd>
                    <dt class="text-muted">{{ __('resale.field.billed_to') }}</dt>
                    <dd>
                        @if ($billedTo !== null)
                            <a href="{{ route('customers.show', $billedTo) }}" class="link link-hover">{{ $billedTo->name }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </dd>
                    <dt class="text-muted">{{ __('resale.field.status') }}</dt>
                    <dd><x-status-badge size="xs" :tone="$subscription->status->tone()" :label="$subscription->status->label()" /></dd>
                    @if ($subscription->company_name)
                        <dt class="text-muted">{{ __('resale.field.company_name') }}</dt>
                        <dd>{{ $subscription->company_name }}</dd>
                    @endif
                    @if ($subscription->successor !== null)
                        <dt class="text-muted">{{ __('resale.field.successor') }}</dt>
                        <dd><a href="{{ route('finance.resale.show', $subscription->successor->sqid) }}" class="link link-hover">{{ $subscription->successor->label }} ({{ $subscription->successor->provider->label() }})</a></dd>
                    @endif
                    @foreach ($subscription->predecessors as $predecessor)
                        <dt class="text-muted">{{ __('resale.field.predecessor') }}</dt>
                        <dd><a href="{{ route('finance.resale.show', $predecessor->sqid) }}" class="link link-hover">{{ $predecessor->label }} ({{ $predecessor->provider->label() }})</a></dd>
                    @endforeach
                </dl>
            </x-card>

            <x-card :title="__('resale.section.terms')">
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt class="text-muted">{{ __('resale.field.quantity') }}</dt>
                    <dd class="tabular-nums">{{ $subscription->quantity }}</dd>
                    <dt class="text-muted">{{ __('resale.field.starts_on') }}</dt>
                    <dd class="tabular-nums">{{ $subscription->starts_on->format('d.m.Y') }}</dd>
                    <dt class="text-muted">{{ __('resale.field.ends_on') }}</dt>
                    <dd class="tabular-nums">{{ $subscription->ends_on?->format('d.m.Y') ?? __('resale.value.open_end') }}</dd>
                    <dt class="text-muted">{{ __('resale.field.term_months') }}</dt>
                    <dd class="tabular-nums">{{ $subscription->term_months }}</dd>
                    <dt class="text-muted">{{ __('resale.field.interval') }}</dt>
                    <dd>{{ $subscription->interval->label() }}</dd>
                    <dt class="text-muted">{{ __('resale.field.renewal') }}</dt>
                    <dd>{{ $subscription->renewal->label() }}</dd>
                    @if ($subscription->external_id)
                        <dt class="text-muted">{{ __('resale.field.external_id') }}</dt>
                        <dd class="font-mono text-xs">{{ $subscription->external_id }}</dd>
                    @endif
                    @if ($subscription->external_order_id)
                        <dt class="text-muted">{{ __('resale.field.external_order_id') }}</dt>
                        <dd class="font-mono text-xs">{{ $subscription->external_order_id }}</dd>
                    @endif
                </dl>
            </x-card>

            <x-card :title="__('resale.section.prices')">
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt class="text-muted">{{ __('resale.field.article') }}</dt>
                    <dd>{{ $subscription->productLabel() ?? '—' }}</dd>
                    <dt class="text-muted">{{ __('resale.field.purchase_unit_price') }}</dt>
                    <dd class="tabular-nums">{{ $subscription->purchase_unit_price?->withScale(2)->format() ?? '—' }}</dd>
                    <dt class="text-muted">{{ __('resale.field.sale_unit_price') }}</dt>
                    <dd class="tabular-nums">{{ $subscription->sale_unit_price?->withScale(2)->format() ?? '—' }}</dd>
                    <dt class="text-muted">{{ __('resale.field.expected_sale') }}</dt>
                    <dd class="tabular-nums">{{ $subscription->expectedSalePerPeriod()?->withScale(2)->format() ?? '—' }}</dd>
                    @if ($subscription->purchase_unit_price !== null && $subscription->sale_unit_price !== null)
                        <dt class="text-muted">{{ __('resale.field.margin') }}</dt>
                        <dd class="tabular-nums">{{ $subscription->sale_unit_price->minus($subscription->purchase_unit_price)->withScale(2)->format() }}</dd>
                    @endif
                </dl>
                @if ($subscription->notes)
                    <p class="mt-3 text-sm whitespace-pre-line">{{ $subscription->notes }}</p>
                @endif
            </x-card>
        </div>

        <x-card :title="__('resale.section.periods')" padding="p-0">
            <div class="flex items-center justify-between gap-2 border-b border-base-300 px-4 py-2 text-sm">
                <span class="text-base-content/70">{{ trans_choice('resale.periods.count', $subscription->periods->count(), ['count' => $subscription->periods->count()]) }}</span>
                @if ($openCount > 0)
                    <span class="badge badge-error badge-sm">{{ trans_choice('resale.periods.open_count', $openCount, ['count' => $openCount]) }}</span>
                @else
                    <span class="badge badge-success badge-sm">{{ __('resale.periods.all_decided') }}</span>
                @endif
            </div>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('resale.field.period') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.field.quantity') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.field.expected_sale') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.link.covered') }}</x-table.th>
                        <x-table.th>{{ __('resale.link.links') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                        <x-table.th class="text-right"></x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($subscription->periods as $period)
                    @include('finance.resale._period_row', ['period' => $period, 'subscription' => $subscription, 'showSubscription' => false, 'canManage' => $canManage, 'today' => $today])
                @empty
                    <x-table.empty :colspan="7" :title="__('resale.empty.periods')" compact />
                @endforelse
            </x-table>
        </x-card>
    </x-index-page>
@endsection
