{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _recourse_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Lieferantenregress anlegen (Feature 072, MVP-253/1577). Erwartet: $claim,
     $recourseOptions (suppliers, purchaseOrders, incomingInvoices, articles). Vorbelegt aus der Akte. --}}
@php
    $supplierSqid = fn (?int $id): string => (string) \App\Support\Sqid::encode(\App\Models\Supplier\Supplier::class, $id);
@endphp
<x-modal
    id="claim-recourse-create"
    :embedded="false"
    :title="__('Regress anlegen')"
    :eyebrow="__('Lieferanten-/Herstellerregress')"
    icon="undo"
    tone="primary"
    :badge="$claim->number"
    :action="route('claims.recourses.store', $claim)"
    method="POST"
    :submit-label="__('Regress anlegen')"
>
    <x-form-group :legend="__('claims.recourse.group_claim')" icon="undo" tone="primary" cols="2">
        <x-select-field name="supplier_id" id="claim-recourse-supplier" :label="__('Lieferant')" required span="2"
                        data-prefill-from="purchase_order_id" data-prefill-key="supplier">
            <option value="">—</option>
            @foreach ($recourseOptions['suppliers'] as $supplier)
                <option value="{{ $supplier->sqid }}" @selected($claim->supplier_id === $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="amount_claimed" id="claim-recourse-amount" type="number" step="0.01" min="0" :label="__('Forderung')" />
        <x-input-field name="response_due_days" id="claim-recourse-due-days" type="number" min="1" max="365" step="1"
                       :label="__('validation.attributes.response_due_days')"
                       :placeholder="(string) config('claims.recourse_response_days', 14)"
                       :hint="__('claims.recourse.hint_due_days', ['days' => (int) config('claims.recourse_response_days', 14)])" />
        <x-input-field name="external_reference" id="claim-recourse-reference" :label="__('Externe RMA-Nr.')" maxlength="255" />
        <x-input-field name="serial_no" id="claim-recourse-serial" :label="__('Seriennummer')" maxlength="255" :value="$claim->serial_no" />
        <x-textarea-field name="warranty_terms" id="claim-recourse-terms" :label="__('validation.attributes.warranty_terms')" rows="2" span="2" maxlength="4000"
                          :hint="__('claims.recourse.hint_terms')"></x-textarea-field>
    </x-form-group>

    <x-form-group :legend="__('claims.recourse.group_reference')" icon="link" tone="primary" cols="2">
        @if ($recourseOptions['purchaseOrders']->isNotEmpty())
            <x-select-field name="purchase_order_id" id="claim-recourse-order" :label="__('validation.attributes.purchase_order_id')">
                <option value="">—</option>
                @foreach ($recourseOptions['purchaseOrders'] as $order)
                    <option value="{{ $order->sqid }}" data-prefill-supplier="{{ $supplierSqid($order->supplier_id) }}"
                            @selected($claim->purchase_order_id === $order->id)>{{ $order->number }}@if ($order->supplier !== null) · {{ $order->supplier->name }}@endif</option>
                @endforeach
            </x-select-field>
        @endif
        @if ($recourseOptions['incomingInvoices']->isNotEmpty())
            <x-select-field name="incoming_einvoice_id" id="claim-recourse-invoice" :label="__('claims.recourse.field_incoming_invoice')">
                <option value="">—</option>
                @foreach ($recourseOptions['incomingInvoices'] as $incoming)
                    <option value="{{ $incoming->sqid }}">{{ $incoming->invoice_number ?? '#' . $incoming->id }}@if ($incoming->seller_name) · {{ $incoming->seller_name }}@endif @if ($incoming->issue_date !== null) · {{ $incoming->issue_date->fdate() }}@endif</option>
                @endforeach
            </x-select-field>
        @endif
        @if ($recourseOptions['articles']->isNotEmpty())
            <x-select-field name="article_id" id="claim-recourse-article" :label="__('Artikel')" span="2">
                <option value="">—</option>
                @foreach ($recourseOptions['articles'] as $article)
                    <option value="{{ $article->sqid }}" @selected($claim->article_id === $article->id)>{{ $article->number ? $article->number . ' · ' : '' }}{{ $article->name }}</option>
                @endforeach
            </x-select-field>
        @endif
    </x-form-group>
</x-modal>
