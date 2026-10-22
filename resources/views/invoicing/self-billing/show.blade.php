{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gutschrift im Gutschriftsverfahren (MVP-1280). Variablen: $credit, $totals, $blockers.
--}}
@extends('layouts.app')
@section('title', __('invoicing.self_billing.document_title') . ' ' . ($credit->number ?? ''))
@section('nav-title', __('invoicing.self_billing.document_title'))

@section('content')
<x-index-page :subtitle="$credit->supplier->company ?: $credit->supplier->name"
              :back="route('invoices.self-billing.index')" :back-label="__('Zurück')">
    <x-slot:actions>
        @if ($credit->isDraft())
            @can(\App\Enums\User\Permission::InvoiceCreate->value)
                <x-icon-btn icon="playlist_add" size="sm" data-entry-modal-trigger
                            :href="route('invoices.self-billing.lines.create', $credit)" show-label>{{ __('invoicing.self_billing.action.add_line') }}</x-icon-btn>
            @endcan
            @can(\App\Enums\User\Permission::InvoiceIssue->value)
                @if ($blockers === [])
                    <x-action-form :action="route('invoices.self-billing.issue', $credit)" :confirm="__('invoicing.self_billing.confirm.issue')">
                        <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit" show-label>{{ __('invoicing.self_billing.action.issue') }}</x-icon-btn>
                    </x-action-form>
                @endif
            @endcan
        @endif
        <x-icon-btn icon="picture_as_pdf" size="sm" target="_blank" :href="route('invoices.self-billing.pdf', $credit)" show-label>{{ __('invoicing.self_billing.action.pdf') }}</x-icon-btn>
        @unless ($credit->isDraft())
            <x-icon-btn icon="code" size="sm" placement="menu" :href="route('invoices.self-billing.xml', $credit)" show-label>{{ __('invoicing.self_billing.action.xml') }}</x-icon-btn>
        @endunless
        @if ($credit->status === \App\Enums\Invoicing\SelfBillingStatus::Issued)
            @can(\App\Enums\User\Permission::InvoiceIssue->value)
                <x-icon-btn icon="block" tone="error" size="sm" placement="danger" data-entry-modal-trigger
                            :href="route('invoices.self-billing.cancel-dialog', $credit)" show-label>{{ __('invoicing.self_billing.action.cancel') }}</x-icon-btn>
            @endcan
        @endif
        <x-help-button topic="invoices.self-billing" />
    </x-slot:actions>

    <div class="space-y-4">
        @if ($blockers !== [])
            <div class="alert alert-warning text-sm" role="status">
                <x-icon name="info" />
                <div>
                    <div class="font-medium">{{ __('invoicing.self_billing.blockers') }}</div>
                    <ul class="list-disc pl-5">
                        @foreach ($blockers as $blocker)
                            <li>{{ $blocker }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <x-card :title="__('invoicing.self_billing.document_title')" icon="receipt_long">
            <x-detail-grid>
                <x-detail-grid.row :label="__('invoicing.self_billing.field.status')"><x-status-badge :tone="$credit->status->tone()" size="sm">{{ $credit->status->label() }}</x-status-badge></x-detail-grid.row>
                <x-detail-grid.row :label="__('invoicing.self_billing.field.number')" :value="$credit->number ?? '—'" />
                <x-detail-grid.row :label="__('invoicing.self_billing.field.supplier')">
                    <a href="{{ route('suppliers.show', $credit->supplier) }}" class="link link-hover">{{ $credit->supplier->company ?: $credit->supplier->name }}</a>
                </x-detail-grid.row>
                <x-detail-grid.row :label="__('invoicing.self_billing.field.agreement')" :value="$credit->supplier->self_billing_agreed_on?->fdate() ?? '—'" />
                <x-detail-grid.row :label="__('invoicing.self_billing.field.service_period')" :value="($credit->service_from?->fdate() ?? '—') . ' – ' . ($credit->service_until?->fdate() ?? '—')" />
                @if ($credit->issued_on)
                    <x-detail-grid.row :label="__('invoicing.self_billing.field.issued_on')" :value="$credit->issued_on->fdate() . ' · ' . ($credit->issuer?->name ?? '')" />
                @endif
                @if ($credit->incoming?->document)
                    <x-detail-grid.row :label="__('invoicing.self_billing.field.incoming')">
                        <a href="{{ route('finance.incoming-invoices.show', $credit->incoming->document) }}" class="link link-hover">{{ $credit->incoming->status->label() }}</a>
                    </x-detail-grid.row>
                @endif
                @if ($credit->cancelled_at)
                    <x-detail-grid.row :label="__('invoicing.self_billing.field.cancel_reason')" :value="$credit->cancelled_at->fdate() . ': ' . $credit->cancel_reason" />
                @endif
                @if ($credit->note)
                    <x-detail-grid.row :label="__('invoicing.self_billing.field.note')" :value="$credit->note" />
                @endif
            </x-detail-grid>
        </x-card>

        <x-card :title="__('invoicing.self_billing.field.lines')" icon="list" :count="$credit->lines->count()">
            @if ($credit->lines->isEmpty())
                <x-empty-state icon="playlist_add" :title="__('invoicing.self_billing.empty_lines')" />
            @else
                <x-table>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('invoicing.self_billing.field.description') }}</th>
                            <th class="text-right">{{ __('invoicing.self_billing.field.quantity') }}</th>
                            <th class="text-right">{{ __('invoicing.self_billing.field.unit_price') }}</th>
                            <th class="text-right">{{ __('invoicing.self_billing.field.tax_rate') }}</th>
                            <th class="text-right">{{ __('invoicing.self_billing.field.net') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($credit->lines as $line)
                        <tr>
                            <td>{{ $line->description }}</td>
                            <td class="text-right whitespace-nowrap">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $line->lineQuantity(), 2) }} {{ $line->unit }}</td>
                            <td class="text-right whitespace-nowrap">{{ $line->unitPrice()?->format() ?? '—' }}</td>
                            <td class="text-right whitespace-nowrap">{{ $line->taxRate()?->format() ?? '0 %' }}</td>
                            <td class="text-right whitespace-nowrap">{{ $line->netAmount()->format() }}</td>
                            <td class="text-right">
                                @if ($credit->isDraft())
                                    @can(\App\Enums\User\Permission::InvoiceCreate->value)
                                        <x-action-form :action="route('invoices.self-billing.lines.destroy', [$credit, $line])" method="DELETE" :confirm="__('invoicing.self_billing.confirm.remove_line')">
                                            <x-icon-btn icon="delete" tone="ghost" size="sm" type="submit" :label="__('invoicing.self_billing.action.remove_line')" />
                                        </x-action-form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
                <div class="mt-3 flex flex-col items-end gap-1 text-sm">
                    <div>{{ __('invoicing.self_billing.field.net') }}: <strong>{{ $totals['subtotal']->format() }}</strong></div>
                    <div>{{ __('invoicing.self_billing.field.tax') }}: {{ $totals['tax_amount']->format() }}</div>
                    <div class="text-base">{{ __('invoicing.self_billing.field.gross') }}: <strong>{{ $totals['total']->format() }}</strong></div>
                </div>
            @endif
        </x-card>
    </div>
</x-index-page>
@endsection
