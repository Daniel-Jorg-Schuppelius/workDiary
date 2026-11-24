{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Auftrag lesen wie im Altportal (admin/kp_detail.php, Archiv: kp_detail_a.php). Erwartet: $order, $archived --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_support.orders.show_title', ['number' => $order->id]))

@section('content')
    <p><a href="{{ \App\Http\Middleware\RememberListUrl::urlFor($archived ? 'legacy.support.archive.index' : 'legacy.support.orders.index') }}">{{ __('legacy_support.classic.to_list') }}</a></p>

    @php
        $orderModel = \App\Legacy\Models\LegacySupportOrder::class;
        $statusText = match (true) {
            $order->isOverdue() => null,
            (int) $order->status === $orderModel::STATUS_NEW => __('legacy_support.classic.legend.new'),
            (int) $order->status === $orderModel::STATUS_SEEN => __('legacy_support.classic.legend.seen'),
            (int) $order->status === $orderModel::STATUS_SCHEDULED => __('legacy_support.classic.legend.scheduled'),
            (int) $order->status === $orderModel::STATUS_DONE => __('legacy_support.classic.legend.done'),
            (int) $order->status === $orderModel::STATUS_ARCHIVED => __('legacy_support.classic.legend.archived'),
            default => (string) $order->status,
        };
    @endphp
    <table class="eintrag">
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.number') }}</td><td>{{ $order->id }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.customer') }}</td><td>{{ $order->customer?->kunde ?? __('legacy_support.customer_unknown', ['id' => $order->kunde]) }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.date') }}</td><td>{{ \App\Legacy\Support\LegacyClock::format($order->datum) }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.orderer') }}</td><td>{{ $order->kname }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.phone') }}</td><td>{{ $order->telefon }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.object') }}</td><td>{{ $order->objekt }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.order_text') }}</td><td class="kp-pre">{{ $order->auftrag }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.appointment') }}</td><td>{{ \App\Legacy\Support\LegacyClock::format($order->appointmentAt()) ?? '-' }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.mechanic') }}</td><td>{{ $order->wer }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.reply') }}</td><td class="kp-pre">{{ $order->antwort }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.internal') }}</td><td class="kp-pre">{{ $order->intern }}</td></tr>
        <tr><td class="kp-label">{{ __('legacy_support.classic.label.duration') }}</td><td class="kp-pre">{{ $order->dauer }}</td></tr>
        <tr>
            <td class="kp-label">{{ __('legacy_support.classic.label.status') }}</td>
            <td>
                @if ($statusText === null)
                    <span class="kp-overdue-text">{{ __('legacy_support.classic.overdue') }}</span>
                @else
                    {{ $statusText }}
                @endif
            </td>
        </tr>
    </table>
@endsection
