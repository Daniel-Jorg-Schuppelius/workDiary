{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customs-invoice.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Handels- bzw. Proformarechnung für Sendungen außerhalb der EU (MVP-1007). --}}
<x-pdf-layout pdf-type="delivery_note" :pdf-title="$title . ' ' . $number">
    <h1>{{ $title }} {{ $number }}</h1>
    <div class="meta">
        {{ __('shipping.customs.pdf.date') }}: <strong>{{ $date }}</strong>
        · {{ __('shipping.customs.pdf.delivery_note') }}: <strong>{{ $deliveryNoteNumber }}</strong>
        @if ($invoiceNumber)
            · {{ __('shipping.customs.pdf.invoice') }}: <strong>{{ $invoiceNumber }}</strong>
        @endif
        @if ($tracking)
            · {{ __('shipping.customs.pdf.tracking') }}: <strong>{{ $tracking }}</strong>
        @endif
    </div>

    <table class="grid2" style="margin-top: 10pt;">
        <tr>
            <td>
                <strong>{{ __('shipping.customs.pdf.sender') }}</strong><br>
                {{ $sender['name'] }}<br>
                @foreach ($sender['lines'] as $line){{ $line }}<br>@endforeach
                @if ($sender['vat_id'] !== '')
                    {{ __('shipping.customs.pdf.vat_id') }}: {{ $sender['vat_id'] }}<br>
                @endif
                @if ($sender['eori'] !== '')
                    {{ __('shipping.customs.pdf.eori') }}: {{ $sender['eori'] }}
                @endif
            </td>
            <td>
                <strong>{{ __('shipping.customs.pdf.recipient') }}</strong><br>
                {{ $recipient['name'] }}<br>
                @foreach ($recipient['lines'] as $line){{ $line }}<br>@endforeach
                @if ($recipient['vat_id'] !== '')
                    {{ __('shipping.customs.pdf.vat_id') }}: {{ $recipient['vat_id'] }}
                @endif
            </td>
        </tr>
    </table>

    <div class="meta" style="margin-top: 8pt;">
        {{ __('shipping.customs.pdf.reason') }}: <strong>{{ $reason->label() }}</strong>
        · {{ __('shipping.customs.pdf.currency') }}: <strong>{{ $position['currency'] }}</strong>
    </div>

    <table style="margin-top: 10pt;">
        <thead>
            <tr>
                <th>{{ __('shipping.customs.pdf.col.description') }}</th>
                <th>{{ __('shipping.customs.pdf.col.tariff') }}</th>
                <th>{{ __('shipping.customs.pdf.col.origin') }}</th>
                <th style="text-align: right;">{{ __('shipping.customs.pdf.col.quantity') }}</th>
                <th style="text-align: right;">{{ __('shipping.customs.pdf.col.net_weight') }}</th>
                <th style="text-align: right;">{{ __('shipping.customs.pdf.col.unit_value') }}</th>
                <th style="text-align: right;">{{ __('shipping.customs.pdf.col.total_value') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $position['description'] }}@if ($position['sku'])<br><span class="meta">{{ $position['sku'] }}</span>@endif</td>
                <td>{{ $position['tariff'] }}</td>
                <td>{{ $position['origin'] }}</td>
                <td style="text-align: right;">{{ $position['quantity'] }} {{ $position['unit'] }}</td>
                <td style="text-align: right;">{{ $position['net_weight'] }}</td>
                <td style="text-align: right;">{{ $position['unit_value'] }}</td>
                <td style="text-align: right;">{{ $position['total_value'] }}</td>
            </tr>
        </tbody>
    </table>

    <table class="grid2" style="margin-top: 10pt;">
        <tr>
            <td>
                {{ __('shipping.customs.pdf.total_net_weight') }}: <strong>{{ $position['net_weight'] }} kg</strong><br>
                @if ($grossWeight !== null)
                    {{ __('shipping.customs.pdf.gross_weight') }}: <strong>{{ $grossWeight }} kg</strong><br>
                @endif
                @if ($parcels > 0)
                    {{ __('shipping.customs.pdf.parcels') }}: <strong>{{ $parcels }}</strong>
                @endif
            </td>
            <td style="text-align: right;">
                {{ __('shipping.customs.pdf.total_value') }}: <strong>{{ $position['total_value'] }}</strong>
            </td>
        </tr>
    </table>

    @if (! $commercial)
        <div class="meta" style="margin-top: 8pt;">{{ __('shipping.customs.pdf.no_sale') }}</div>
    @endif

    <p style="margin-top: 16pt;">{{ __('shipping.customs.pdf.declaration') }}</p>
    <table class="grid2" style="margin-top: 24pt;">
        <tr>
            <td>________________________________<br>{{ __('shipping.customs.pdf.place_date') }}</td>
            <td>________________________________<br>{{ __('shipping.customs.pdf.signature') }}</td>
        </tr>
    </table>
</x-pdf-layout>
