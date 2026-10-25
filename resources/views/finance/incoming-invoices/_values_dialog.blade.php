{{--
  Created on   : Sat Oct 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _values_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Werte eines Klärfalls bzw. einer Erkennung erfassen oder korrigieren (Feature 163, MVP-1110),
     mit dem Original daneben. --}}
@php($isOutgoing = $incoming->direction === \App\Enums\Billing\DocumentDirection::Outgoing)
{{-- Teilweise erkannte Werte eines Klärfalls (summary.extraction.found) belegen die Felder vor. --}}
@php($found = (array) data_get($incoming->summary, 'extraction.found', []))
{{-- Datum aus dem Lieferdatum (MVP-1215) nur als Vorschlag: vorbelegt, aber gekennzeichnet. --}}
@php($hints = (array) data_get($incoming->summary, 'extraction.hints', []))
@php($corrects = $incoming->recognition === \App\Enums\Invoicing\IncomingInvoiceRecognition::Extracted || data_get($incoming->summary, 'manual') !== null)
<x-modal
    :title="$corrects ? __('Werte korrigieren') : __('Werte erfassen')"
    :eyebrow="$incoming->document?->title"
    icon="edit_note"
    :action="route('finance.incoming-invoices.values', $incoming)"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
    :size="$previewMime !== null ? 'wide' : 'standard'"
>
    <div @class(['grid gap-4', 'lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-start' => $previewMime !== null])>
        @if ($previewMime !== null)
            {{-- Bleibt beim Scrollen zu den Beträgen stehen; Höhe aus der Dialoghöhe abzüglich Kopf und Fuß. --}}
            <div class="overflow-hidden rounded-box border border-base-300 lg:sticky lg:top-0">
                @include('finance.incoming-invoices._preview', ['document' => $incoming->document, 'height' => 'h-[40dvh] lg:h-[calc(var(--wd-dialog-max-h)-11rem)]'])
            </div>
        @endif
        <div class="space-y-4">
            <x-form-group :legend="__('Rechnung')" icon="receipt_long" cols="2" :description="__('Werte am Original ablesen; das Original bleibt unverändert.')">
                <x-input-field name="invoice_number" :label="__('Rechnungsnummer')" :value="old('invoice_number', $incoming->invoice_number ?? $found['number'] ?? null)" required />
                <x-input-field name="currency" :label="__('Währung')" :value="old('currency', $incoming->currency?->value ?? 'EUR')" required />
                <x-input-field name="issue_date" type="date" :label="__('Rechnungsdatum')" :value="old('issue_date', $incoming->issue_date?->toDateString() ?? $found['issued_on'] ?? $hints['issue_date'] ?? null)" :hint="! isset($found['issued_on']) && isset($hints['issue_date']) && $incoming->issue_date === null ? __('Vorschlag aus dem Lieferdatum, bitte am Original prüfen.') : null" required />
                <x-input-field name="due_date" type="date" :label="__('Fällig am')" :value="old('due_date', $incoming->due_date?->toDateString() ?? $found['due_on'] ?? null)" />
                <x-input-field name="party_name" :label="$isOutgoing ? __('Käufer') : __('Verkäufer')" :value="old('party_name', $isOutgoing ? $incoming->buyer_name : $incoming->seller_name)" />
                <x-input-field name="party_vat_id" :label="__('USt-IdNr.')" :value="old('party_vat_id', $isOutgoing ? $incoming->buyer_vat_id : $incoming->seller_vat_id)" />
            </x-form-group>

            <x-form-group :legend="__('Beträge')" icon="euro" cols="3">
                <x-input-field name="amount_net" :label="__('Netto')" inputmode="decimal" :value="old('amount_net', $incoming->amount_net?->getAmount() ?? $found['net'] ?? null)" />
                <x-input-field name="amount_tax" :label="__('Steuer')" inputmode="decimal" :value="old('amount_tax', $incoming->amount_tax?->getAmount() ?? $found['tax'] ?? null)" />
                <x-input-field name="amount_gross" :label="__('Brutto')" inputmode="decimal" :value="old('amount_gross', $incoming->amount_gross?->getAmount() ?? $found['gross'] ?? null)" required />
            </x-form-group>
        </div>
    </div>

    <x-validation-errors />
</x-modal>
