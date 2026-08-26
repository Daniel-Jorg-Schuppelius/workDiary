{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : incoming.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('procurement.ui.incoming_title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('procurement.ui.incoming_title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('procurement.ui.incoming_subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('purchase-orders.index')" show-label>{{ __('procurement.title') }}</x-icon-btn>
    </x-slot:actions>

    @if ($lines->isEmpty())
        <x-empty-state framed icon="local_shipping"
                       :title="__('procurement.ui.incoming_none')" />
    @else
        <x-table :zebra="true" scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('procurement.field.expected_at') }}</th>
                    <th>{{ __('procurement.field.number') }}</th>
                    <th>{{ __('procurement.field.supplier') }}</th>
                    <th>{{ __('procurement.field.article') }}</th>
                    <th class="text-right">{{ __('procurement.field.ordered_qty') }}</th>
                    <th class="text-right">{{ __('procurement.field.received_qty') }}</th>
                    <th class="text-right">{{ __('procurement.ui.open') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line->purchaseOrder?->expected_at?->format('d.m.Y') ?? '—' }}</td>
                    <td><a href="{{ route('purchase-orders.show', $line->purchaseOrder) }}" class="link link-hover font-mono">{{ $line->purchaseOrder?->number }}</a></td>
                    <td>{{ $line->purchaseOrder?->supplier?->name }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="text-right tabular-nums">{{ $line->ordered_qty }} {{ $line->unit }}</td>
                    <td class="text-right tabular-nums">{{ $line->received_qty }}</td>
                    <td class="text-right tabular-nums font-semibold">{{ $line->openQty() }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="7" icon="local_shipping" :title="__('procurement.ui.incoming_none')" />
            @endforelse
        </x-table>
    @endif
</x-index-page>
@endsection
