{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Auftrag aus Kundensicht wie im Altportal (kunde/kp_detail.php). Erwartet: $customer, $order --}}
@extends('legacy.classic.layout')
@section('title', __('legacy_support.portal.order_title', ['number' => $order->id]))

@section('content')
    @include('legacy.classic.support._portal_menu', ['customer' => $customer])
    <p>{{ __('legacy_support.classic.detail_view') }}</p>
    <p><a href="{{ route('legacy.portal.orders.index') }}">{{ __('legacy_support.classic.to_list') }}</a></p>

    <table class="eintrag">
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.date') }}</td><td>{{ \App\Legacy\Support\LegacyClock::format($order->datum) }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.name') }}</td><td>{{ $order->kname }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.phone') }}</td><td>{{ $order->telefon }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.object') }}</td><td>{{ $order->objekt }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.order') }}</td><td class="kp-pre">{{ $order->auftrag }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.appointment') }}</td><td>{{ \App\Legacy\Support\LegacyClock::format($order->appointmentAt()) ?? __('legacy_support.classic.open') }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.replies') }}</td><td class="kp-pre">{{ $order->antwort !== '' ? $order->antwort : __('legacy_support.classic.none') }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.status') }}</td><td>{{ $order->customerStatusLabel() }}</td></tr>
    </table>
@endsection
