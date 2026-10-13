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
        <strong>{{ optional($note->delivered_on)->format('d.m.Y') }}</strong>
        @if ($note->orderConfirmation)
            · {{ __('Auftragsbestätigung') }}: <strong>{{ $note->orderConfirmation->number }}</strong>
        @endif
        @if ($note->manufacturingOrder)
            · {{ __('manufacturing.delivery_note.order') }}: <strong>{{ $note->manufacturingOrder->number }}</strong>
        @endif
    </div>

    <table class="grid2" style="margin-top: 10pt;">
        <tr>
            <td>
                <strong>{{ __('manufacturing.delivery_note.recipient') }}</strong><br>
                @if ($note->customer)
                    {{ $note->customer->displayLabel() }}<br>
                    @if ($note->customer->address_street){{ $note->customer->address_street }}<br>@endif
                    {{ $note->customer->address_zip }} {{ $note->customer->address_city }}<br>
                    {{ $note->customer->country }}
                @else
                    {{ __('manufacturing.delivery_note.no_customer') }}
                @endif
            </td>
            <td>
                @if ($warehouses->isNotEmpty())
                    <strong>{{ __('manufacturing.delivery_note.warehouse') }}</strong><br>
                    {{ $warehouses->implode(', ') }}
                @endif
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
            @foreach ($note->items as $item)
                @if ($item->line_kind !== \App\Enums\Billing\DocumentLineKind::Item)
                    <tr>
                        <td></td>
                        <td colspan="3" @if ($item->line_kind === \App\Enums\Billing\DocumentLineKind::Title) style="font-weight: bold;" @endif>{{ $item->description }}</td>
                    </tr>
                    @continue
                @endif
                <tr>
                    <td>{{ $item->sku }}</td>
                    <td style="white-space: pre-line;">{{ $item->description }}</td>
                    <td style="text-align: right;">{{ \CommonToolkit\Helper\Data\NumberHelper::trimTrailingZeros(\CommonToolkit\ValueObjects\Decimal::of((string) $item->quantity, 4)->format(), ',') }}</td>
                    <td>{{ $item->unit }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (filled($note->note))
        <div class="meta" style="margin-top: 12pt; white-space: pre-line;">{{ $note->note }}</div>
    @endif
    <div class="meta" style="margin-top: 16pt;">{{ __('manufacturing.delivery_note.footer_note') }}</div>
</x-pdf-layout>
