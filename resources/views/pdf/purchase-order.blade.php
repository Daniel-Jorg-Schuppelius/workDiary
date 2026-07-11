<x-pdf-layout pdf-type="purchase_order" :pdf-title="__('procurement.pdf.title') . ' ' . $order->number">
    <h1>{{ __('procurement.pdf.title') }} {{ $order->number }}</h1>
    <div class="meta">
        {{ __('procurement.pdf.date') }}:
        <strong>{{ optional($order->created_at)->format('d.m.Y') }}</strong>
        @if ($order->expected_at)
            · {{ __('procurement.pdf.expected') }}: <strong>{{ $order->expected_at->format('d.m.Y') }}</strong>
        @endif
    </div>

    <table class="grid2" style="margin-top: 10pt;">
        <tr>
            <td>
                <strong>{{ __('procurement.pdf.supplier') }}</strong><br>
                @if ($order->supplier)
                    {{ $order->supplier->company ?: $order->supplier->name }}<br>
                    @if ($order->supplier->address_street){{ $order->supplier->address_street }}<br>@endif
                    {{ $order->supplier->address_zip }} {{ $order->supplier->address_city }}<br>
                    {{ $order->supplier->country }}
                @endif
            </td>
            <td>
                <strong>{{ __('procurement.pdf.deliver_to') }}</strong><br>
                {{ $order->warehouse?->name }}
            </td>
        </tr>
    </table>

    <table style="margin-top: 12pt;">
        <thead>
            <tr>
                <th>{{ __('procurement.pdf.col.sku') }}</th>
                <th>{{ __('procurement.pdf.col.name') }}</th>
                <th style="text-align: right;">{{ __('procurement.pdf.col.qty') }}</th>
                <th>{{ __('procurement.pdf.col.unit') }}</th>
                <th style="text-align: right;">{{ __('procurement.pdf.col.unit_price') }}</th>
                <th style="text-align: right;">{{ __('procurement.pdf.col.line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->lines as $line)
                @php($lineTotal = bcmul((string) $line->ordered_qty, (string) ($line->unit_price ?? '0'), 2))
                <tr>
                    <td>{{ $line->supplier_sku ?: $line->article->sku }}</td>
                    <td>{{ $line->article->name }}</td>
                    <td style="text-align: right;">{{ rtrim(rtrim((string) $line->ordered_qty, '0'), '.') }}</td>
                    <td>{{ $line->unit }}</td>
                    <td style="text-align: right;">{{ $line->unit_price !== null ? number_format((float) $line->unit_price, 2, ',', '.') : '—' }}</td>
                    <td style="text-align: right;">{{ number_format((float) $lineTotal, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top: 8pt;">
        <tr>
            <td style="text-align: right;"><strong>{{ __('procurement.pdf.total') }}</strong></td>
            <td style="text-align: right; width: 25%;">
                <strong>{{ number_format((float) $total, 2, ',', '.') }} {{ $order->currency->value }}</strong>
            </td>
        </tr>
    </table>

    @if ($order->note)
        <div class="meta" style="margin-top: 12pt;">{{ __('procurement.pdf.note') }}: {{ $order->note }}</div>
    @endif
</x-pdf-layout>
