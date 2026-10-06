{{--
  Created on   : Sun Jun 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : invoice-reconciliation.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('procurement.reconcile.title') . ' — ' . $order->number)
@section('nav-title', __('procurement.title'))

@php
    /** @var \App\Models\Procurement\PurchaseOrder $order */
    /** @var array $result */
    $invoice = $result['invoice'];
    $money = fn ($v) => $v === null ? '—' : ($v instanceof \CommonToolkit\ValueObjects\Money ? $v->format() : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true) . ' €');
    $qty = fn ($v) => $v === null ? '—' : rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 3, withThousandsSeparator: true), '0'), ',');
    $tones = ['match' => 'success', 'mismatch' => 'warning', 'invoice_only' => 'error'];
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('procurement.reconcile.title')" :subtitle="$order->number"
                        :back="route('purchase-orders.show', $order)" :back-label="__('procurement.reconcile.back')" />
    </x-slot:toolbar>

    {{-- Gesamtergebnis --}}
    <div class="alert {{ $result['ok'] ? 'alert-success' : 'alert-warning' }}">
        <x-icon :name="$result['ok'] ? 'check_circle' : 'warning'" />
        <span>{{ $result['ok'] ? __('procurement.reconcile.ok') : __('procurement.reconcile.has_discrepancies') }}</span>
    </div>

    {{-- Rechnungskopf --}}
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('procurement.reconcile.invoice_header') }}</h2>
        <x-detail-grid layout="cells" :cols="4">
            <x-detail-grid.row :label="__('procurement.reconcile.number')" class="font-medium">{{ $invoice->getNumber() }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('procurement.reconcile.doc_type')">{{ $invoice->isCreditNote() ? __('procurement.reconcile.credit_note') : __('procurement.reconcile.invoice') }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('procurement.reconcile.date')">{{ $invoice->getDate()->format('d.m.Y') }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('procurement.reconcile.due_date')">{{ $invoice->getDueDate()?->format('d.m.Y') ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('procurement.reconcile.net')" class="tabular-nums">{{ $money($invoice->getNetTotal()) }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('procurement.reconcile.vat')" class="tabular-nums">{{ $money($invoice->getVatAmount()) }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('procurement.reconcile.gross')" class="tabular-nums font-medium">{{ $money($invoice->getGrossTotal()) }}</x-detail-grid.row>
        </x-detail-grid>
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
            <div class="w-full max-w-xs">
                <x-detail-grid layout="split">
                    <x-detail-grid.row :label="__('procurement.reconcile.invoice_net_total')" class="tabular-nums">{{ $money($result['totals']['invoice_net']) }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('procurement.reconcile.order_net_total')" class="tabular-nums">{{ $money($result['totals']['order_net']) }}</x-detail-grid.row>
                </x-detail-grid>
                {{-- Summenzeile: eigene Liste, damit Linie und Gewicht an der Zeile hängen --}}
                <x-detail-grid layout="split" class="mt-1 border-t pt-1 font-medium">
                    <x-detail-grid.row :label="__('procurement.reconcile.totals')"><x-status-badge :tone="$result['totals']['matches'] ? 'success' : 'warning'">{{ $result['totals']['matches'] ? __('procurement.reconcile.match_short') : __('procurement.reconcile.diff_short') }}</x-status-badge></x-detail-grid.row>
                </x-detail-grid>
            </div>
        </div>
    </x-card>
</x-page-shell>
@endsection
