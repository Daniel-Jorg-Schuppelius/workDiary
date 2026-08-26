{{--
  Created on   : Fri Aug 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : import-review.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('invoice-import.review_title', ['nr' => $invoice->number]))
@section('nav-title', __('invoice-import.review_nav'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-slot:toolbar>
        <x-page-toolbar :title="__('invoice-import.review_title', ['nr' => $invoice->number])"
                        :badge="$reviewed ? __('invoice-import.review_badge_done') : __('invoice-import.review_badge_open')"
                        :badge-tone="$reviewed ? 'success' : 'warning'">
            <div class="text-sm text-base-content/70">{{ $invoice->customer->name }}</div>
            <x-slot:actions>
                <x-icon-btn icon="download" tone="ghost" size="sm"
                            :href="route('invoices.pdf-import.source', $invoice)"
                            show-label>{{ __('invoice-import.original') }}</x-icon-btn>
                <x-icon-btn icon="data_object" tone="info" size="sm"
                            data-entry-modal-trigger
                            :href="route('invoices.einvoice-options.edit', $invoice)"
                            show-label>{{ __('invoice-import.options_action') }}</x-icon-btn>
                <x-icon-btn icon="receipt_long" tone="ghost" size="sm"
                            :href="route('invoices.show', $invoice)"
                            show-label>{{ __('invoice-import.review_back_to_invoice') }}</x-icon-btn>
                @unless ($reviewed)
                    <x-action-form :action="route('invoices.import-review.confirm', $invoice)">
                        <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit"
                                    show-label>{{ __('invoice-import.review_confirm') }}</x-icon-btn>
                    </x-action-form>
                @endunless
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @php $warnings = (array) ($extraction['warnings'] ?? []); @endphp
    @if ($warnings !== [])
        <div class="alert alert-warning">
            <x-icon name="warning" />
            <ul class="list-inside list-disc text-sm">
                @foreach ($warnings as $warning)
                    <li>{{ __("invoice-import.warning.$warning") }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('invoice-import.review_original')" icon="description" padding="p-0">
            @if ($hasPreview)
                <object data="{{ route('invoices.pdf-import.preview', $invoice) }}"
                        type="{{ $source === 'xml' ? 'text/xml' : 'application/pdf' }}"
                        class="h-[75vh] w-full">
                    <p class="p-4 text-sm text-muted">{{ __('invoice-import.review_no_preview') }}</p>
                </object>
            @else
                <div class="flex flex-col items-start gap-3 p-4">
                    <p class="text-sm text-muted">{{ __('invoice-import.review_no_preview') }}</p>
                    <x-icon-btn icon="download" tone="primary" size="sm"
                                :href="route('invoices.pdf-import.source', $invoice)"
                                show-label>{{ __('invoice-import.original') }}</x-icon-btn>
                </div>
            @endif
        </x-card>

        <div class="space-y-4">
            <x-card :title="__('invoice-import.review_detected')" icon="fact_check" padding="p-0">
                @php
                    $money = fn ($v) => $v === null || $v === '' ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true);
                    $skonto = is_array($extraction['skonto'] ?? null) ? $extraction['skonto'] : null;
                    $fields = [
                        ['label' => __('invoice-import.invoice_number'), 'detected' => $extraction['number'] ?? null, 'current' => $invoice->number],
                        ['label' => __('invoice-import.issue_date'), 'detected' => $extraction['issued_on'] ?? null, 'current' => $invoice->issued_on?->toDateString()],
                        ['label' => __('invoice-import.due_date'), 'detected' => $extraction['due_on'] ?? null, 'current' => $invoice->due_on?->toDateString()],
                        ['label' => __('invoice-import.currency'), 'detected' => $extraction['currency'] ?? null, 'current' => $invoice->currency?->value],
                        ['label' => __('invoice-import.review_net'), 'detected' => $money($extraction['net'] ?? null), 'current' => $money($invoice->subtotal?->getAmount())],
                        ['label' => __('invoice-import.review_tax'), 'detected' => $money($extraction['tax'] ?? null), 'current' => $money($invoice->tax_amount?->getAmount())],
                        ['label' => __('invoice-import.review_gross'), 'detected' => $money($extraction['gross'] ?? null), 'current' => $money($invoice->total?->getAmount())],
                        ['label' => __('invoice-import.review_tax_rate'), 'detected' => $extraction['tax_rate'] ?? null, 'current' => $invoice->tax_rate?->getNumericValue()],
                        ['label' => __('invoice-import.buyer_reference'), 'detected' => $extraction['buyer_reference'] ?? null, 'current' => $invoice->buyer_reference],
                        ['label' => __('invoice-import.review_skonto'), 'detected' => $skonto !== null ? __('invoice-import.review_skonto_value', ['percent' => $skonto['percent'], 'days' => $skonto['days']]) : null, 'current' => $invoice->hasSkonto() ? __('invoice-import.review_skonto_value', ['percent' => $invoice->skonto_percent?->getNumericValue(), 'days' => (int) $invoice->skonto_days]) : null],
                        ['label' => __('invoice-import.review_payment_terms'), 'detected' => $extraction['payment_terms_days'] ?? null, 'current' => $invoice->payment_terms_days],
                        ['label' => __('invoice-import.review_iban'), 'detected' => data_get($extraction, 'payment.iban'), 'current' => null],
                        ['label' => __('invoice-import.review_seller_vat'), 'detected' => $extraction['seller_vat'] ?? data_get($extraction, 'seller.vat_id'), 'current' => null],
                    ];
                @endphp
                <x-table bare>
                    <thead>
                        <tr>
                            <th>{{ __('invoice-import.review_field') }}</th>
                            <th>{{ __('invoice-import.review_detected_value') }}</th>
                            <th>{{ __('invoice-import.review_current_value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fields as $field)
                            @continue($field['detected'] === null && $field['current'] === null)
                            <tr>
                                <td class="text-base-content/70">{{ $field['label'] }}</td>
                                <td>{{ $field['detected'] ?? '—' }}</td>
                                <td>{{ $field['current'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            </x-card>

            <x-card :title="__('invoice-import.review_items')" icon="list_alt" padding="p-0">
                <x-table bare>
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>{{ __('invoice-import.review_item_description') }}</th>
                            <th class="text-right">{{ __('invoice-import.review_item_quantity') }}</th>
                            <th>{{ __('invoice-import.review_item_unit') }}</th>
                            <th class="text-right">{{ __('invoice-import.review_item_price') }}</th>
                            <th class="text-right">{{ __('invoice-import.review_item_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td>{{ $item->position }}</td>
                                <td class="whitespace-pre-line">{{ $item->description }}</td>
                                <td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->quantity, 2) }}</td>
                                <td>{{ $item->unit }}</td>
                                <td class="text-right">{{ $item->unit_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($item->unit_price->toFloat(), 2, withThousandsSeparator: true) : '—' }}</td>
                                <td class="text-right">{{ $item->amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($item->amount->toFloat(), 2, withThousandsSeparator: true) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
                <p class="px-4 py-3 text-xs text-muted">{{ __('invoice-import.review_items_hint') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
