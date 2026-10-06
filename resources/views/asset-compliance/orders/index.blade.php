{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Prüfaufträge an Dienstleister (MVP-938). Erwartet: $orders, $canManage --}}
@extends('layouts.app')

@section('title', __('inspection_order.title'))
@section('nav-title', __('inspection_order.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('inspection_order.subtitle')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" size="sm" tone="primary" data-entry-modal-trigger :href="route('asset-compliance.orders.create')" show-label>{{ __('inspection_order.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>
    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('inspection_order.field.title') }}</th>
                <th>{{ __('inspection_order.field.supplier') }}</th>
                <th class="text-right">{{ __('inspection_order.field.items') }}</th>
                <th>{{ __('inspection_order.field.status') }}</th>
                <th class="text-right">{{ __('inspection_order.field.offer_amount') }}</th>
                <th class="text-right">{{ __('Aktionen') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($orders as $order)
            <tr class="hover">
                <td>{{ $order->title }}</td>
                <td>{{ $order->supplier?->name }}</td>
                <td class="text-right tabular-nums">{{ $order->items_count }}</td>
                <td><span class="wd-badge badge-ghost">{{ $order->status->label() }}</span></td>
                <td class="text-right tabular-nums">{{ $order->offer_amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($order->offer_amount, 2, withThousandsSeparator: true) . ' ' . $order->currency : '—' }}</td>
                <td><div class="flex justify-end"><x-icon-btn icon="visibility" size="xs" :href="route('asset-compliance.orders.show', $order)" :label="__('inspection_order.open')" /></div></td>
            </tr>
        @empty
            <x-table.empty icon="handshake" :colspan="6" :title="__('inspection_order.empty')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$orders" standing />
</x-index-page>
@endsection
