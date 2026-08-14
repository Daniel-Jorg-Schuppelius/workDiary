{{--
  Created on   : Sun Jun 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : delivery-note.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-pdf-layout pdf-type="delivery_note" :pdf-title="__('manufacturing.delivery_note.title') . ' ' . $number">
    <h1>{{ __('manufacturing.delivery_note.title') }} {{ $number }}</h1>
    <div class="meta">
        {{ __('manufacturing.delivery_note.date') }}:
        <strong>{{ optional($delivery->delivered_at)->format('d.m.Y') }}</strong>
        @if ($delivery->order)
            · {{ __('manufacturing.delivery_note.order') }}: <strong>{{ $delivery->order->number }}</strong>
        @endif
    </div>

    <table class="grid2" style="margin-top: 10pt;">
        <tr>
            <td>
                <strong>{{ __('manufacturing.delivery_note.recipient') }}</strong><br>
                @if ($delivery->customer)
                    {{ $delivery->customer->company ?: $delivery->customer->name }}<br>
                    @if ($delivery->customer->address_street){{ $delivery->customer->address_street }}<br>@endif
                    {{ $delivery->customer->address_zip }} {{ $delivery->customer->address_city }}<br>
                    {{ $delivery->customer->country }}
                @else
                    {{ __('manufacturing.delivery_note.no_customer') }}
                @endif
            </td>
            <td>
                <strong>{{ __('manufacturing.delivery_note.warehouse') }}</strong><br>
                {{ $delivery->warehouse?->name }}
            </td>
        </tr>
    </table>

    <table style="margin-top: 12pt;">
        <thead>
            <tr>
                <th>{{ __('manufacturing.delivery_note.col.sku') }}</th>
                <th>{{ __('manufacturing.delivery_note.col.name') }}</th>
                <th style="text-align: right;">{{ __('manufacturing.delivery_note.col.qty') }}</th>
                <th>{{ __('manufacturing.delivery_note.col.unit') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $delivery->sku_snapshot }}</td>
                <td>{{ $delivery->name_snapshot }}</td>
                <td style="text-align: right;">{{ rtrim(rtrim((string) $delivery->quantity, '0'), '.') }}</td>
                <td>{{ $delivery->unit }}</td>
            </tr>
        </tbody>
    </table>

    <div class="meta" style="margin-top: 16pt;">{{ __('manufacturing.delivery_note.footer_note') }}</div>
</x-pdf-layout>
