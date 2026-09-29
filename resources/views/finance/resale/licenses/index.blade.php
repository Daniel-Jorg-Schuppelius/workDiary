{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lizenzbestand (Feature 152, MVP-1024): aktueller Bestand je Produkt und
  Paket — unabhängig vom Header-Zeitraum — und die Einzellizenzen mit Status.
  Schlüssel erscheinen hier nie, auch nicht maskiert als Wert.
--}}

@extends('layouts.app')

@section('title', __('resale.license.title'))
@section('nav-title', __('resale.title.menu'))
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('resale.license.subtitle')">
        <x-slot:actions>
            @can(\App\Enums\User\Permission::ResellingManage->value)
                <x-icon-btn icon="add_shopping_cart" tone="primary" size="sm" placement="bar" data-entry-modal-trigger
                            :href="route('finance.resale.licenses.sell.create', array_filter(['product' => $filters['product'] ?: null]))"
                            show-label>{{ __('resale.license.action.sell') }}</x-icon-btn>
                <x-icon-btn icon="inventory" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.licenses.batches.create', array_filter(['product' => $filters['product'] ?: null]))"
                            show-label>{{ __('resale.license.action.new_batch') }}</x-icon-btn>
                <x-icon-btn icon="add" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.licenses.products.create')"
                            show-label>{{ __('resale.license.action.new_product') }}</x-icon-btn>
            @endcan
        </x-slot:actions>

        @include('finance.resale._tabs')

        <div class="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-4">
            <x-kpi-tile :label="__('resale.license.summary.available')" :value="$summary['available']" tone="success" />
            <x-kpi-tile :label="__('resale.license.summary.sold')" :value="$summary['sold']" />
            <x-kpi-tile :label="__('resale.license.summary.incomplete')" :value="$summary['incomplete']" :tone="$summary['incomplete'] > 0 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('resale.license.summary.reorder')" :value="$summary['reorder']" :tone="$summary['reorder'] > 0 ? 'error' : 'neutral'" />
        </div>

        <x-filter-bar :action="route('finance.resale.licenses.index')" :reset="route('finance.resale.licenses.index')">
            <input type="search" name="q" value="{{ $filters['q'] }}" class="input input-sm input-bordered w-48"
                   placeholder="{{ __('resale.license.filter.search') }}" aria-label="{{ __('resale.license.filter.search') }}">
            <select name="product" class="select select-sm select-bordered w-48" aria-label="{{ __('resale.license.field.product') }}">
                <option value="">{{ __('resale.license.filter.all_products') }}</option>
                @foreach ($productOptions as $option)
                    <option value="{{ $option->sqid }}" @selected($filters['product'] === $option->sqid)>{{ $option->name }}</option>
                @endforeach
            </select>
            <select name="status" class="select select-sm select-bordered w-40" aria-label="{{ __('resale.license.field.status') }}">
                <option value="">{{ __('resale.license.filter.all_statuses') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select name="customer" class="select select-sm select-bordered w-48" aria-label="{{ __('resale.license.field.customer') }}">
                <option value="">{{ __('resale.license.filter.all_customers') }}</option>
                @foreach ($customerOptions as $option)
                    <option value="{{ $option->sqid }}" @selected($filters['customer'] === $option->sqid)>{{ $option->name }}</option>
                @endforeach
            </select>
            <x-filter-toggle name="reorder" :label="__('resale.license.filter.reorder')" :checked="$filters['reorder']" tone="error" />
        </x-filter-bar>

        {{-- Aktueller Bestand je Produkt und Paket (nicht vom Zeitraum abhängig). --}}
        <x-card :title="__('resale.license.stock_title')" icon="inventory_2" padding="p-0" class="mb-4 flex-none">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('resale.license.field.product') }}</th>
                        <th class="text-right">{{ __('resale.license.field.purchased') }}</th>
                        <th class="text-right">{{ __('resale.license.status.available') }}</th>
                        <th class="text-right">{{ __('resale.license.status.sold') }}</th>
                        <th class="text-right">{{ __('resale.license.status.incomplete') }}</th>
                        <th class="text-right">{{ __('resale.license.status.blocked') }}</th>
                        <th class="text-right">{{ __('resale.license.field.reorder_level') }}</th>
                        <th>{{ __('resale.license.field.hint') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                @forelse ($products as $product)
                    @php($row = $stock[$product->id])
                    <tr>
                        <td>
                            <span class="font-medium">{{ $product->name }}</span>
                            @if ($product->manufacturer)
                                <span class="block text-xs text-muted">{{ $product->manufacturer }}</span>
                            @endif
                            @if ($product->batches->isNotEmpty())
                                <span class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($product->batches as $batch)
                                        @php($bc = $batchCounts[$batch->id] ?? ['available' => 0, 'purchased' => 0])
                                        <a href="{{ route('finance.resale.licenses.batches.show', $batch) }}" class="badge badge-ghost badge-sm hover:badge-outline"
                                           title="{{ __('resale.license.batch_badge_title', ['date' => $batch->purchased_on->fdate()]) }}">{{ $batch->reference }} · {{ $bc['available'] }}/{{ $bc['purchased'] }}</a>
                                    @endforeach
                                </span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $row['purchased'] }}</td>
                        <td class="text-right tabular-nums font-semibold">{{ $row['available'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['sold'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['incomplete'] }}</td>
                        <td class="text-right tabular-nums">{{ $row['blocked'] }}</td>
                        <td class="text-right tabular-nums">{{ $product->reorder_level ?? '—' }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @if ($row['reorder'])
                                    <x-status-badge size="xs" tone="error" :label="$row['sold_out'] ? __('resale.license.hint.sold_out') : __('resale.license.hint.reorder')" />
                                @elseif ($product->reorder_level !== null)
                                    <x-status-badge size="xs" tone="success" :label="__('resale.license.hint.sufficient')" />
                                @endif
                                @if ($row['incomplete'] > 0)
                                    <x-status-badge size="xs" tone="warning" :label="__('resale.license.hint.keys_missing')" />
                                @endif
                            </div>
                        </td>
                        <td class="text-right">
                            @can(\App\Enums\User\Permission::ResellingManage->value)
                                <div class="flex justify-end gap-1">
                                    <x-icon-btn icon="add_shopping_cart" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.sell.create', ['product' => $product->sqid])" :title="__('resale.license.action.sell')" />
                                    <x-icon-btn icon="inventory" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.batches.create', ['product' => $product->sqid])" :title="__('resale.license.action.new_batch')" />
                                    <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.licenses.products.edit', $product)" :title="__('resale.license.action.edit_product')" />
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="9" icon="key" :title="__('resale.license.empty.products')" :message="__('resale.license.empty.products_hint')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('resale.license.field.license') }}</th>
                    <th>{{ __('resale.license.field.product') }}</th>
                    <th>{{ __('resale.license.field.status') }}</th>
                    <th>{{ __('resale.license.field.keys') }}</th>
                    <th>{{ __('resale.license.field.holder') }}</th>
                    <th>{{ __('resale.license.field.sold_on') }}</th>
                    <th>{{ __('resale.license.field.invoice_reference') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($units as $unit)
                @include('finance.resale.licenses._unit_row', ['unit' => $unit, 'showProduct' => true])
            @empty
                <x-table.empty :colspan="8" icon="key" :title="__('resale.license.empty.units')" compact />
            @endforelse
        </x-table>
        <x-pagination :paginator="$units" standing />
    </x-index-page>
@endsection
