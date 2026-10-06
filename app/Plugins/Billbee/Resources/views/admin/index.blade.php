{{--
  Created on   : Sat Jul 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('billbee::billbee.title'))
@section('nav-title', __('billbee::billbee.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('billbee::billbee.intro')">
    <x-slot:badges>
        @if ($openInbox > 0)
            <x-status-badge tone="warning">{{ __('billbee::billbee.open_inbox', ['count' => $openInbox]) }}</x-status-badge>
        @endif
        @if ($lastSyncAt)
            <x-status-badge>{{ __('billbee::billbee.last_sync', ['at' => \Illuminate\Support\Carbon::parse($lastSyncAt)->diffForHumans()]) }}</x-status-badge>
        @endif
    </x-slot:badges>
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.billbee.sync') }}">
            @csrf
            <x-icon-btn icon="sync" tone="primary" size="sm" type="submit" show-label>{{ __('billbee::billbee.action.sync') }}</x-icon-btn>
        </form>
    </x-slot:actions>


    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.billbee.index') }}" class="flex flex-wrap items-end gap-2">
        <label class="form-control">
            <span class="label-text text-xs">{{ __('billbee::billbee.field.channel') }}</span>
            <select name="channel" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('billbee::billbee.filter.all_channels') }}</option>
                @foreach ($channels as $option)
                    <option value="{{ $option }}" @selected($channel === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text text-xs">{{ __('billbee::billbee.field.state') }}</span>
            <input type="number" name="state" value="{{ $state }}" class="input input-sm input-bordered w-24" min="0" max="99" />
        </label>
        <x-button type="submit" tone="plain">{{ __('billbee::billbee.filter.apply') }}</x-button>
    </form>

    {{-- Bestellspiegel --}}
    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('billbee::billbee.field.order_number') }}</th>
                <th>{{ __('billbee::billbee.field.channel') }}</th>
                <th>{{ __('billbee::billbee.field.state') }}</th>
                <th>{{ __('billbee::billbee.field.buyer') }}</th>
                <th>{{ __('billbee::billbee.field.customer') }}</th>
                <th class="text-right">{{ __('billbee::billbee.field.total') }}</th>
                <th>{{ __('billbee::billbee.field.ordered_at') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($orders as $order)
            <tr>
                <td class="font-mono text-xs">{{ $order->order_number ?? $order->billbee_order_id }}</td>
                <td><x-status-badge>{{ $order->channel ?? '—' }}</x-status-badge></td>
                <td>{{ $order->stateLabel() }}</td>
                <td>{{ data_get($order->buyer, 'FullName') ?? data_get($order->buyer, 'Email') ?? '—' }}</td>
                <td>
                    @if ($order->customer)
                        {{ $order->customer->name }}
                    @else
                        <x-status-badge tone="warning">{{ __('billbee::billbee.status.open_assignment') }}</x-status-badge>
                    @endif
                </td>
                {{-- Anzeige-Makros statt Roh-Formatierung (Vollaudit 2026-07, N52). --}}
                <td class="text-right font-mono text-xs">{{ $order->total_gross?->format(withSymbol: false) ?? '0,00' }} {{ $order->currency?->value }}</td>
                <td class="text-xs">{{ $order->ordered_at?->fdatetime() ?? '—' }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="7" icon="shopping_cart" :title="__('billbee::billbee.empty')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$orders" standing />
</x-index-page>
@endsection
