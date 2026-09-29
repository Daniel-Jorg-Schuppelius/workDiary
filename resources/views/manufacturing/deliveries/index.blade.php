{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lieferscheinliste (MVP-1013). Variablen: $deliveries, $shipping, $search
--}}
@extends('layouts.app')
@section('title', __('manufacturing.delivery_list.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('manufacturing.delivery_list.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('manufacturing.delivery_list.subtitle')">
    <x-tab-nav :items="collect(\App\Http\Controllers\Manufacturing\DeliveryListController::SHIPPING_FILTERS)->map(fn (string $filter): array => [
        'label' => __('manufacturing.delivery_list.filter.' . $filter),
        'route' => 'deliveries.index',
        'params' => array_filter(['shipping' => $filter === 'all' ? null : $filter, 'q' => $search ?: null]),
        'active' => $shipping === $filter,
    ])->all()" />

    <x-filter-bar :action="route('deliveries.index')" method="GET" :reset="route('deliveries.index')">
        <input type="hidden" name="shipping" value="{{ $shipping === 'all' ? '' : $shipping }}">
        <input type="text" name="q" value="{{ $search }}" class="input input-sm input-bordered w-56 shrink-0"
               placeholder="{{ __('manufacturing.delivery_list.search') }}" aria-label="{{ __('manufacturing.delivery_list.search') }}" />
    </x-filter-bar>

    <x-table :zebra="true" scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('manufacturing.delivery_note.title') }}</th>
                <th>{{ __('manufacturing.delivery_note.date') }}</th>
                <th>{{ __('manufacturing.delivery_note.recipient') }}</th>
                <th>{{ __('manufacturing.delivery_note.col.name') }}</th>
                <th class="text-right">{{ __('manufacturing.delivery_note.col.qty') }}</th>
                <th>{{ __('manufacturing.delivery_note.order') }}</th>
                <th>{{ __('shipping.label_short') }}</th>
                <th class="text-right"><span class="sr-only">{{ __('Aktionen') }}</span></th>
            </tr>
        </x-slot:head>
        @forelse ($deliveries as $delivery)
            <tr>
                <td class="font-mono">LS-{{ str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT) }}</td>
                <td class="tabular-nums">{{ $delivery->delivered_at?->orgTz()->format('d.m.Y') }}</td>
                <td>{{ $delivery->customer?->displayLabel() ?? '—' }}</td>
                <td>{{ $delivery->name_snapshot }}@if ($delivery->sku_snapshot)<span class="text-xs text-muted"> · {{ $delivery->sku_snapshot }}</span>@endif</td>
                <td class="text-right tabular-nums">{{ $delivery->quantity !== null ? \CommonToolkit\Helper\Data\NumberHelper::trimTrailingZeros($delivery->quantity->getValue()->format(), ',') : '' }} {{ $delivery->unit }}</td>
                <td>
                    @if ($delivery->order)
                        <a href="{{ route('manufacturing-orders.show', $delivery->order) }}" class="link link-hover font-mono">{{ $delivery->order->number ?? '—' }}</a>
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if ($delivery->shipment)
                        <span class="badge badge-sm">{{ $delivery->shipment->status->label() }}</span>
                        @if ($delivery->shipment->tracking_number)
                            <span class="text-xs text-muted">{{ strtoupper($delivery->shipment->carrier) }}: {{ $delivery->shipment->tracking_number }}</span>
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-right whitespace-nowrap">
                    @if ($delivery->order)
                        <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" target="_blank"
                                    :href="route('manufacturing-orders.deliveries.pdf', [$delivery->order, $delivery])" :label="__('manufacturing.delivery_note.title')" />
                        <x-icon-btn icon="mail" size="sm" tone="ghost" data-entry-modal-trigger
                                    :href="route('manufacturing-orders.deliveries.mail.form', [$delivery->order, $delivery])" :label="__('Per E-Mail senden')" />
                        @can('update', $delivery->order)
                            @if ($delivery->customer_id)
                                <x-icon-btn icon="public" size="sm" tone="ghost" data-entry-modal-trigger
                                            :href="route('manufacturing-orders.deliveries.customs.form', [$delivery->order, $delivery])" :label="__('shipping.customs.action')" />
                            @endif
                        @endcan
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="8" :title="__('manufacturing.delivery_list.empty')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$deliveries" standing />
</x-index-page>
@endsection
