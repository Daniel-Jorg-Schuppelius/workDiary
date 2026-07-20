@extends('layouts.app')
@section('title', __('procurement.reconcile.title') . ' — ' . $order->number)
@section('nav-title', __('procurement.title'))

@php
    /** @var \App\Models\PurchaseOrder $order */
    /** @var array $result */
    $invoice = $result['invoice'];
    $money = fn ($v) => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true) . ' €';
    $qty = fn ($v) => $v === null ? '—' : rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 3, withThousandsSeparator: true), '0'), ',');
    $tones = ['match' => 'success', 'mismatch' => 'warning', 'invoice_only' => 'error'];
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('procurement.reconcile.title')" :subtitle="$order->number">
            <x-slot:actions>
                <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-sm btn-ghost gap-1">
                    <span class="material-symbols-rounded text-base">arrow_back</span>{{ __('procurement.reconcile.back') }}
                </a>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Gesamtergebnis --}}
    <div class="alert {{ $result['ok'] ? 'alert-success' : 'alert-warning' }}">
        <span class="material-symbols-rounded">{{ $result['ok'] ? 'check_circle' : 'warning' }}</span>
        <span>{{ $result['ok'] ? __('procurement.reconcile.ok') : __('procurement.reconcile.has_discrepancies') }}</span>
    </div>

    {{-- Rechnungskopf --}}
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('procurement.reconcile.invoice_header') }}</h2>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            <div><dt class="opacity-60">{{ __('procurement.reconcile.number') }}</dt><dd class="font-medium">{{ $invoice->getNumber() }}</dd></div>
            <div><dt class="opacity-60">{{ __('procurement.reconcile.doc_type') }}</dt><dd>{{ $invoice->isCreditNote() ? __('procurement.reconcile.credit_note') : __('procurement.reconcile.invoice') }}</dd></div>
            <div><dt class="opacity-60">{{ __('procurement.reconcile.date') }}</dt><dd>{{ $invoice->getDate()->format('d.m.Y') }}</dd></div>
            <div><dt class="opacity-60">{{ __('procurement.reconcile.due_date') }}</dt><dd>{{ $invoice->getDueDate()?->format('d.m.Y') ?? '—' }}</dd></div>
            <div><dt class="opacity-60">{{ __('procurement.reconcile.net') }}</dt><dd class="tabular-nums">{{ $money($invoice->getNetTotal()) }}</dd></div>
            <div><dt class="opacity-60">{{ __('procurement.reconcile.vat') }}</dt><dd class="tabular-nums">{{ $money($invoice->getVatAmount()) }}</dd></div>
            <div><dt class="opacity-60">{{ __('procurement.reconcile.gross') }}</dt><dd class="tabular-nums font-medium">{{ $money($invoice->getGrossTotal()) }}</dd></div>
        </dl>
    </x-card>

    {{-- Positionsabgleich --}}
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('procurement.reconcile.positions') }}</h2>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('procurement.reconcile.sku') }}</th>
                    <th>{{ __('procurement.reconcile.name') }}</th>
                    <th class="text-right">{{ __('procurement.reconcile.invoice_qty') }}</th>
                    <th class="text-right">{{ __('procurement.reconcile.order_qty') }}</th>
                    <th class="text-right">{{ __('procurement.reconcile.invoice_net') }}</th>
                    <th class="text-right">{{ __('procurement.reconcile.order_net') }}</th>
                    <th>{{ __('procurement.reconcile.status') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($result['lines'] as $line)
                <tr @class(['bg-warning/10' => $line['status'] !== 'match'])>
                    <td class="font-mono text-xs">{{ $line['sku'] ?: '—' }}</td>
                    <td>{{ $line['name'] }}</td>
                    <td class="text-right tabular-nums">{{ $qty($line['invoice_qty']) }}</td>
                    <td class="text-right tabular-nums">{{ $qty($line['order_qty']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($line['invoice_net']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($line['order_net']) }}</td>
                    <td><x-status-badge :tone="$tones[$line['status']] ?? 'ghost'">{{ __('procurement.reconcile.line_status.' . $line['status']) }}</x-status-badge></td>
                </tr>
            @endforeach
            @forelse ($result['missing'] as $miss)
                <tr class="bg-error/10">
                    <td class="font-mono text-xs">{{ $miss['sku'] ?: '—' }}</td>
                    <td>{{ $miss['name'] }}</td>
                    <td class="text-right tabular-nums">—</td>
                    <td class="text-right tabular-nums">{{ $qty($miss['order_qty']) }}</td>
                    <td class="text-right tabular-nums">—</td>
                    <td class="text-right tabular-nums">{{ $money($miss['order_net']) }}</td>
                    <td><x-status-badge tone="error">{{ __('procurement.reconcile.line_status.missing') }}</x-status-badge></td>
                </tr>
            @empty
            @endforelse
        </x-table>

        {{-- Summenvergleich --}}
        <div class="mt-4 flex justify-end">
            <dl class="text-sm w-full max-w-xs space-y-1">
                <div class="flex justify-between"><dt class="opacity-60">{{ __('procurement.reconcile.invoice_net_total') }}</dt><dd class="tabular-nums">{{ $money($result['totals']['invoice_net']) }}</dd></div>
                <div class="flex justify-between"><dt class="opacity-60">{{ __('procurement.reconcile.order_net_total') }}</dt><dd class="tabular-nums">{{ $money($result['totals']['order_net']) }}</dd></div>
                <div class="flex justify-between border-t pt-1 font-medium">
                    <dt>{{ __('procurement.reconcile.totals') }}</dt>
                    <dd><x-status-badge :tone="$result['totals']['matches'] ? 'success' : 'warning'">{{ $result['totals']['matches'] ? __('procurement.reconcile.match_short') : __('procurement.reconcile.diff_short') }}</x-status-badge></dd>
                </div>
            </dl>
        </div>
    </x-card>
</x-page-shell>
@endsection
