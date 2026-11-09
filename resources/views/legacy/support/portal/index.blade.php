{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.public')

@section('title', __('legacy_support.portal.my_orders'))
@section('header-action')
    @include('legacy.support.portal._logout')
@endsection

@section('content')
    @include('legacy.support.portal._frame', ['customer' => $customer, 'active' => 'orders'])

    <x-table table-sort="server" :route="route('legacy.portal.orders.index')" :current-sort="$sort" :current-dir="$dir" :caption="__('legacy_support.portal.my_orders')">
        <x-slot:head>
            <tr>
                <x-table.th sort="datum" class="w-40">{{ __('legacy_support.field.date') }}</x-table.th>
                <x-table.th sort="name" class="w-40">{{ __('legacy_support.field.name') }}</x-table.th>
                <x-table.th sort="telefon" class="w-36">{{ __('legacy_support.field.phone') }}</x-table.th>
                <x-table.th sort="objekt">{{ __('legacy_support.field.object_order') }}</x-table.th>
                <x-table.th sort="termin" class="w-40">{{ __('legacy_support.field.appointment') }}</x-table.th>
                <x-table.th sort="status" class="w-36">{{ __('legacy_support.field.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($orders as $order)
            <tr class="hover">
                <td class="whitespace-nowrap">
                    <a href="{{ route('legacy.portal.orders.show', $order->id) }}" class="link link-hover">{{ \App\Legacy\Support\LegacyClock::format($order->datum) ?? '—' }}</a>
                </td>
                <td>{{ $order->kname !== '' ? $order->kname : '—' }}</td>
                <td class="whitespace-nowrap">{{ $order->telefon !== '' ? $order->telefon : '—' }}</td>
                <td class="text-sm">
                    <a href="{{ route('legacy.portal.orders.show', $order->id) }}" class="link link-hover">
                        {{ \CommonToolkit\Helper\Data\StringHelper::truncate(trim($order->objekt . ', ' . $order->auftrag, ', '), 100) }}
                    </a>
                </td>
                <td class="whitespace-nowrap">{{ \App\Legacy\Support\LegacyClock::format($order->appointmentAt()) ?? __('legacy_support.portal.appointment_open') }}</td>
                <td><x-status-badge :tone="$order->customerStatusTone()">{{ $order->customerStatusLabel() }}</x-status-badge></td>
            </tr>
        @empty
            <x-table.empty icon="support" :colspan="6" :title="__('legacy_support.portal.empty')" compact />
        @endforelse
    </x-table>

    <div class="mt-4">
        <x-pagination :paginator="$orders" />
    </div>
@endsection
