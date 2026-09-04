{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Reselling-Register (Feature 152, MVP-758): alle Abos mit Halter, Laufzeit,
  Preisen und der Zahl offener Perioden. Ohne Statusfilter nur planbare Abos
  (aktiv, gekündigt); beendete und abgelöste über den Filter.
--}}
@extends('layouts.app')
@section('title', __('resale.title.index'))
@section('nav-title', __('resale.title.menu'))
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('resale.subtitle')">
        <x-slot:actions>
            @can(\App\Enums\User\Permission::ResellingManage->value)
                <x-icon-btn icon="insights" tone="ghost" size="sm" :href="route('finance.resale.report.index')" show-label>{{ __('resale.report.title') }}</x-icon-btn>
                <x-icon-btn icon="price_check" tone="ghost" size="sm" :href="route('finance.resale.prices')" show-label>{{ __('resale.prices.title') }}</x-icon-btn>
                <x-icon-btn icon="shopping_cart" tone="ghost" size="sm" :href="route('finance.resale.purchases.index')" show-label>{{ __('resale.purchase.title') }}</x-icon-btn>
                <x-icon-btn icon="fact_check" :tone="$summary['open_periods'] > 0 ? 'warning' : 'ghost'" size="sm"
                            :href="route('finance.resale.periods.index')"
                            show-label>{{ __('resale.periods.title') }}@if ($summary['open_periods'] > 0) ({{ $summary['open_periods'] }})@endif</x-icon-btn>
                <x-icon-btn icon="inbox" :tone="$summary['unassigned'] > 0 ? 'warning' : 'ghost'" size="sm"
                            :href="route('finance.resale.inbox')"
                            show-label>{{ __('resale.inbox.title') }}@if ($summary['unassigned'] > 0) ({{ $summary['unassigned'] }})@endif</x-icon-btn>
                <x-icon-btn icon="upload" tone="ghost" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.import.create')"
                            show-label>{{ __('resale.import.action') }}</x-icon-btn>
                <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.create', array_filter(['customer' => $filterCustomer?->sqid]))"
                            show-label>{{ __('resale.action.new') }}</x-icon-btn>
            @endcan
        </x-slot:actions>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <x-kpi-tile :label="__('resale.summary.active')" :value="$summary['active']" />
            <x-kpi-tile :label="__('resale.summary.open_periods')" :value="$summary['open_periods']" :tone="$summary['open_periods'] > 0 ? 'warning' : 'success'" />
            <x-kpi-tile :label="__('resale.summary.unassigned')" :value="$summary['unassigned']" :tone="$summary['unassigned'] > 0 ? 'warning' : 'neutral'" />
        </div>

        <x-filter-bar :action="route('finance.resale.index')" :reset="route('finance.resale.index')">
            <input type="search" name="q" value="{{ $filters['q'] }}" class="input input-sm input-bordered w-48"
                   placeholder="{{ __('resale.filter.search') }}" aria-label="{{ __('resale.filter.search') }}">
            <select name="kind" class="select select-sm select-bordered w-40" aria-label="{{ __('resale.field.kind') }}">
                <option value="">{{ __('resale.filter.all_kinds') }}</option>
                @foreach ($kinds as $kind)
                    <option value="{{ $kind->value }}" @selected($filters['kind'] === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </select>
            <select name="provider" class="select select-sm select-bordered w-44" aria-label="{{ __('resale.field.provider') }}">
                <option value="">{{ __('resale.filter.all_providers') }}</option>
                @foreach ($providers as $provider)
                    <option value="{{ $provider->value }}" @selected($filters['provider'] === $provider->value)>{{ $provider->label() }}</option>
                @endforeach
            </select>
            <select name="status" class="select select-sm select-bordered w-40" aria-label="{{ __('resale.field.status') }}">
                <option value="">{{ __('resale.filter.planning') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @if ($filterCustomer !== null)
                <input type="hidden" name="customer" value="{{ $filterCustomer->sqid }}">
                <span class="badge badge-outline badge-sm">{{ $filterCustomer->name }}</span>
            @endif
            <x-filter-toggle name="open" :label="__('resale.filter.open_only')" :checked="$filters['open']" tone="warning" />
        </x-filter-bar>

        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('resale.field.label') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.field.holder') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.kind') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.field.provider') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.field.quantity') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('resale.field.starts_on') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.interval') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.field.sale_unit_price') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.field.open_periods') }}</x-table.th>
                    <x-table.th class="text-right"></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($subscriptions as $subscription)
                <tr class="hover">
                    <td>
                        <a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover font-medium">{{ $subscription->label }}</a>
                        @if ($subscription->productLabel() !== null)
                            <span class="block text-xs text-muted">{{ $subscription->productLabel() }}</span>
                        @endif
                    </td>
                    <td>
                        @if (! $subscription->hasHolder())
                            <x-status-badge size="xs" tone="warning" :label="__('resale.holder.unassigned')" />
                        @else
                            {{ $subscription->holderLabel() }}
                            @if ($subscription->foreignCustomer !== null)
                                <span class="block text-xs text-muted">{{ __('resale.holder.via', ['partner' => $subscription->foreignCustomer->customer?->name]) }}</span>
                            @endif
                        @endif
                    </td>
                    <td><x-icon :name="$subscription->kind->icon()" size="1.1rem" /> <span class="text-sm">{{ $subscription->kind->label() }}</span></td>
                    <td class="text-sm">{{ $subscription->provider->label() }}</td>
                    <td class="text-right tabular-nums">{{ $subscription->quantity }}</td>
                    <td class="whitespace-nowrap tabular-nums">{{ $subscription->starts_on->format('d.m.Y') }}@if ($subscription->ends_on) – {{ $subscription->ends_on->format('d.m.Y') }}@endif</td>
                    <td class="text-sm">{{ $subscription->interval->label() }}</td>
                    <td class="text-right tabular-nums whitespace-nowrap">{{ $subscription->sale_unit_price?->format() ?? '—' }}</td>
                    <td><x-status-badge size="xs" :tone="$subscription->status->tone()" :label="$subscription->status->label()" /></td>
                    <td class="text-right tabular-nums">
                        @if ($subscription->open_periods_count > 0)
                            <span class="badge badge-error badge-sm">{{ $subscription->open_periods_count }}</span>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility" size="xs" tone="ghost" :href="route('finance.resale.show', $subscription->sqid)" :title="__('resale.action.show')" />
                            @can(\App\Enums\User\Permission::ResellingManage->value)
                                <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.edit', $subscription->sqid)" :title="__('resale.action.edit')" />
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="11" icon="subscriptions" :title="__('resale.empty.subscriptions')" compact />
            @endforelse
        </x-table>
        <x-pagination :paginator="$subscriptions" standing />
    </x-index-page>
@endsection
