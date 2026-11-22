{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Auftragsübersicht des Kunden im Aussehen des Altportals (kunde/kp_info.php). Erwartet: $customer, $orders, $sort, $dir --}}
@extends('legacy.classic.layout')
@section('title', __('legacy_support.classic.portal'))

@section('content')
    @include('legacy.classic.support._portal_menu', ['customer' => $customer])

    <div class="kp-scroll">
        <table class="kp-wide kp-list">
            <thead>
                <tr>
                    @include('legacy.classic._sort', ['route' => 'legacy.portal.orders.index', 'key' => 'datum', 'label' => __('legacy_support.classic.col.date')])
                    @include('legacy.classic._sort', ['route' => 'legacy.portal.orders.index', 'key' => 'name', 'label' => __('legacy_support.classic.col.name')])
                    @include('legacy.classic._sort', ['route' => 'legacy.portal.orders.index', 'key' => 'telefon', 'label' => __('legacy_support.classic.col.phone')])
                    @include('legacy.classic._sort', ['route' => 'legacy.portal.orders.index', 'key' => 'objekt', 'label' => __('legacy_support.classic.col.object_order_customer')])
                    @include('legacy.classic._sort', ['route' => 'legacy.portal.orders.index', 'key' => 'termin', 'label' => __('legacy_support.classic.col.appointment')])
                    @include('legacy.classic._sort', ['route' => 'legacy.portal.orders.index', 'key' => 'status', 'label' => __('legacy_support.classic.col.status')])
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php $detail = route('legacy.portal.orders.show', $order->id); @endphp
                    <tr>
                        <td><a href="{{ $detail }}" class="kp-normal">{{ \App\Legacy\Support\LegacyClock::format($order->datum) }}</a></td>
                        <td><a href="{{ $detail }}" class="kp-normal">{{ $order->kname }}</a></td>
                        <td><a href="{{ $detail }}" class="kp-normal">{{ $order->telefon }}</a></td>
                        <td><a href="{{ $detail }}" class="kp-normal">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($order->objekt . ', ' . $order->auftrag, 55) }}</a></td>
                        <td class="kp-center"><a href="{{ $detail }}" class="kp-normal">{{ \App\Legacy\Support\LegacyClock::format($order->appointmentAt()) }}</a></td>
                        <td class="kp-center"><a href="{{ $detail }}" aria-label="{{ __('legacy_support.classic.action.view') }}">@include('legacy.classic.support._customer_status', ['order' => $order])</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="kp-center">{{ __('legacy_support.portal.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('legacy.classic._pager', ['paginator' => $orders])
@endsection
