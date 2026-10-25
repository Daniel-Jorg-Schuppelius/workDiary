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

            {{-- Je Steuersatz eine Zeile; die Kopfwerte Netto und Steuer sind ihre Summen. --}}
            <x-form-group :legend="__('Beträge')" icon="euro" :description="__('Je Steuersatz eine Zeile. Nur ein Satz ohne Beträge: Netto und Steuer werden aus dem Brutto herausgerechnet.')">
                <x-input-field name="amount_gross" :label="__('Brutto')" inputmode="decimal" :value="old('amount_gross', $incoming->amount_gross?->getAmount() ?? $found['gross'] ?? null)" required />
                <div x-data="taxBreakdown"
                     data-items="{{ json_encode(\App\Services\Invoicing\EInvoice\IncomingInvoiceTaxBreakdown::prefill($incoming, $found)) }}"
                     class="space-y-2">
                    <template x-for="(it, i) in items" :key="i">
                        {{-- Schmal: oben Satz und Entfernen, darunter Netto und Steuer. --}}
                        <div class="grid grid-cols-2 items-end gap-2 sm:grid-cols-[minmax(0,6rem)_minmax(0,1fr)_minmax(0,1fr)_2rem]">
                            <div class="fieldset">
                                <label :for="fieldName(i, 'percent')" class="fieldset-label">{{ __('Steuersatz (%)') }}</label>
                                <input type="text" inputmode="decimal" list="incoming-tax-rates" class="input input-bordered w-full"
                                       :id="fieldName(i, 'percent')" :name="fieldName(i, 'percent')" x-model="it.percent" @change="suggestTax(it)">
                            </div>
                            <div class="fieldset max-sm:order-2">
                                <label :for="fieldName(i, 'net')" class="fieldset-label">{{ __('Netto') }}</label>
                                <input type="text" inputmode="decimal" class="input input-bordered w-full"
                                       :id="fieldName(i, 'net')" :name="fieldName(i, 'net')" x-model="it.net" @change="suggestTax(it)">
                            </div>
                            <div class="fieldset max-sm:order-3">
                                <label :for="fieldName(i, 'tax')" class="fieldset-label">{{ __('Steuer') }}</label>
                                <input type="text" inputmode="decimal" class="input input-bordered w-full"
                                       :id="fieldName(i, 'tax')" :name="fieldName(i, 'tax')" x-model="it.tax">
                            </div>
                            <div class="justify-self-end pb-2 max-sm:order-1">
                                <x-icon-btn icon="close" size="xs" type="button" :label="__('Steuersatz entfernen')" x-show="canRemove()" @click="remove(i)" />
                            </div>
                        </div>
                    </template>
                    <datalist id="incoming-tax-rates"><option value="19"></option><option value="7"></option><option value="0"></option></datalist>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <x-icon-btn icon="add" tone="outline" size="sm" type="button" show-label @click="add()">{{ __('Steuersatz hinzufügen') }}</x-icon-btn>
                        <span class="text-xs tabular-nums text-muted" x-text="sumLabel()"></span>
                    </div>
                    <p class="text-xs text-error" x-show="mismatch()" x-text="mismatchLabel()"></p>
                </div>
            </x-form-group>
        </div>
    </div>

    <x-validation-errors />
</x-modal>
