{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Rechnung :nr', ['nr' => $invoice->number]))
@section('nav-title', $invoice->number)

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($invoice->isCancelled())
        <div class="alert alert-error">
            <span class="material-symbols-outlined" aria-hidden="true">block</span>
            <div>
                <div class="font-bold">{{ __('Storniert') }}@if ($invoice->cancelled_at) – {{ $invoice->cancelled_at->fdatetime() }}@endif</div>
                @if ($invoice->cancel_reason)
                    <div class="text-sm">{{ $invoice->cancel_reason }}</div>
                @endif
            </div>
        </div>
    @endif

    @if ($invoice->isProforma())
        <div class="alert alert-warning">
            <span class="material-symbols-outlined" aria-hidden="true">info</span>
            <div>
                <div class="font-bold">{{ __('Pro-forma-Rechnung — keine Rechnung im umsatzsteuerlichen Sinn.') }}</div>
                <div class="text-sm">{{ __('Kein Umsatz, keine Forderung, keine E-Rechnung. Für die Abrechnung in eine echte Rechnung umwandeln.') }}</div>
            </div>
        </div>
    @endif

    @if ((int) $invoice->dunning_level > 0)
        <div class="alert alert-warning text-sm">
            <span class="material-symbols-outlined" aria-hidden="true">notification_important</span>
            {{ __('Mahnstufe :level — zuletzt gemahnt am :date.', [
                'level' => (int) $invoice->dunning_level,
                'date' => optional($invoice->dunned_at)->fdatetime() ?? '—',
            ]) }}
        </div>
    @endif

    @if ($invoice->isCreditNote() && $invoice->parent)
        <div class="alert alert-info">
            <span class="material-symbols-outlined" aria-hidden="true">undo</span>
            <div>
                {{ __('Korrekturrechnung (Gutschrift) zu') }}
                <a class="link" href="{{ route('invoices.show', $invoice->parent) }}">{{ $invoice->parent->number }}</a>
            </div>
        </div>
    @endif

    {{-- Vollaudit 2026-07 (M27): § 14 Abs. 2 UStG — Widerspruch dokumentieren/anzeigen. --}}
    @if ($invoice->isCreditNote())
        @if ($invoice->objection_at !== null)
            <div class="alert alert-warning">
                <span class="material-symbols-outlined" aria-hidden="true">gavel</span>
                <div>
                    {{ __('Widerspruch dokumentiert am :date', ['date' => $invoice->objection_at->fdatetime()]) }}
                    @if ($invoice->objection_note)<br><span class="text-sm">{{ $invoice->objection_note }}</span>@endif
                </div>
            </div>
        @elseif (auth()->user()?->canManageBilling())
            <details class="rounded-box border border-base-300 bg-base-100 p-3">
                <summary class="cursor-pointer text-sm font-medium">{{ __('Widerspruch dokumentieren (§ 14 Abs. 2 UStG)') }}</summary>
                <form method="POST" action="{{ route('invoices.objection', $invoice) }}" class="mt-2 space-y-2">
                    @csrf
                    <textarea name="objection_note" required minlength="10" maxlength="1000" rows="2"
                              class="textarea textarea-bordered w-full text-sm"
                              placeholder="{{ __('Begründung des Empfänger-Widerspruchs (Pflicht)') }}"></textarea>
                    <x-button type="submit" tone="warning" icon="gavel">{{ __('Widerspruch dokumentieren') }}</x-button>
                </form>
            </details>
        @endif
    @endif

    @if ($invoice->isDownPayment() && ($settledByInvoice ?? null) !== null)
        <div class="alert alert-info">
            <span class="material-symbols-outlined" aria-hidden="true">functions</span>
            <div>
                {{ __('Angerechnet in Schlussrechnung') }}
                <a class="link" href="{{ route('invoices.show', $settledByInvoice) }}">{{ $settledByInvoice->number }}</a>
            </div>
        </div>
    @endif

    {{-- Blockform statt @php(...): Inline-@php + späterer @php…@endphp-Block in derselben Datei
         erzeugt über Blades storePhpBlocks ein ungültiges "<?php(" (kein Open-Tag) — View bricht. --}}
    @php $childCredits = $invoice->isCreditNote() ? collect() : $invoice->creditNotes()->get(); @endphp
    @if ($childCredits->isNotEmpty())
        <div class="alert alert-warning">
            <span class="material-symbols-outlined" aria-hidden="true">undo</span>
            <div>
                {{ __('Es existieren Korrekturrechnungen:') }}
                @foreach ($childCredits as $cn)
                    <a class="link" href="{{ route('invoices.show', $cn) }}">{{ $cn->number }}</a>@if (! $loop->last), @endif
                @endforeach
            </div>
        </div>
    @endif

    @if ($invoice->sent_at)
        <div class="alert alert-success/40 text-sm">
            <span class="material-symbols-outlined" aria-hidden="true">mark_email_read</span>
            {{ __('Zuletzt versendet: :date (:count Versand(e))', [
                'date' => $invoice->sent_at->fdatetime(),
                'count' => $invoice->sent_count,
            ]) }}
        </div>
    @endif

    @if (in_array(($invoice->import_metadata['source'] ?? null), ['pdf', 'docx', 'doc', 'xlsx', 'xls', 'xml'], true))
        @php
            $importExtraction = (array) ($invoice->import_metadata['extraction'] ?? []);
            $importValidation = is_array($importExtraction['validation'] ?? null) ? $importExtraction['validation'] : null;
        @endphp
        <div class="alert alert-info">
            <span class="material-symbols-outlined" aria-hidden="true">document_scanner</span>
            <div class="grow">
                <div class="font-bold">{{ __('invoice-import.imported_notice') }}</div>
                @if (($importExtraction['structured'] ?? false) === true)
                    <div class="text-sm">{{ __('invoice-import.structured_detail', ['profile' => $importExtraction['profile'] ?? '—', 'lines' => count($importExtraction['lines'] ?? [])]) }}</div>
                    @if ($importValidation !== null)
                        <div class="text-sm">
                            @if (($importValidation['kosit_valid'] ?? null) === true)
                                <span class="text-success">{{ __('invoice-import.validation.passed') }}</span>
                            @elseif (($importValidation['kosit_valid'] ?? null) === false)
                                <span class="text-error">{{ __('invoice-import.validation.failed', ['count' => count($importValidation['kosit_errors'] ?? [])]) }}</span>
                            @else
                                <span class="text-base-content/60">{{ __('invoice-import.validation.unavailable') }}</span>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="text-sm">{{ __('invoice-import.imported_detail', ['confidence' => (int) ($importExtraction['confidence'] ?? 0)]) }}</div>
                    @if (count($importExtraction['lines'] ?? []) > 0)
                        <div class="text-sm">{{ __('invoice-import.table_lines_detail', ['lines' => count($importExtraction['lines'])]) }}</div>
                    @endif
                @endif
                @if (($importExtraction['warnings'] ?? []) !== [])
                    <ul class="mt-1 list-inside list-disc text-xs">
                        @foreach ($importExtraction['warnings'] as $warning)
                            <li>{{ __("invoice-import.warning.$warning") }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="flex flex-col items-end gap-2">
                @can('update', $invoice)
                    @if (($invoice->import_metadata['reviewed'] ?? false) !== true)
                        <x-icon-btn icon="fact_check" tone="primary" size="sm"
                                    :href="route('invoices.import-review', $invoice)"
                                    show-label>{{ __('invoice-import.review_action') }}</x-icon-btn>
                    @else
                        <x-icon-btn icon="task_alt" tone="ghost" size="sm"
                                    :href="route('invoices.import-review', $invoice)"
                                    show-label>{{ __('invoice-import.review_badge_done') }}</x-icon-btn>
                    @endif
                @endcan
                <x-icon-btn icon="download" tone="ghost" size="sm"
                            :href="route('invoices.pdf-import.source', $invoice)"
                            show-label>{{ __('invoice-import.original') }}</x-icon-btn>
            </div>
        </div>
    @endif

    <x-slot:toolbar>
        <x-page-toolbar :title="$invoice->documentLabel() . ' ' . $invoice->number" :badge="__($invoice->status)" badge-tone="outline">
            <div class="text-sm text-base-content/70">{{ $invoice->customer->name }}</div>
            @if ($invoice->hasServicePeriod())
                <div class="text-sm text-base-content/70">{{ $invoice->dateLabelPeriod() }}: {{ $invoice->serviceDateFrom()->fdate() }} – {{ $invoice->serviceDateTo()->fdate() }}</div>
            @elseif ($invoice->serviceDateSingle())
                <div class="text-sm text-base-content/70">{{ $invoice->dateLabelSingle() }}: {{ $invoice->serviceDateSingle()->fdate() }}</div>
            @endif
            <x-slot:actions>
                <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('invoices.pdf', $invoice)" show-label>{{ __('PDF') }}</x-icon-btn>
                {{-- E-Rechnung (Feature 045): XRechnung nur im Pfad „WorkDiary führt" und für gestellte/bezahlte Rechnungen. --}}
                @php $einvoiceVisible = in_array($invoice->status, [\App\Models\Invoice::STATUS_ISSUED, \App\Models\Invoice::STATUS_PAID], true) && ! app(\App\Services\Finance\BillingModeResolver::class)->effectiveFor($invoice->customer)->isExternal(); @endphp
                @if ($einvoiceVisible)
                    <x-icon-btn icon="receipt" size="sm" :href="route('invoices.einvoice', $invoice)" show-label
                                :title="__('invoicing.einvoice.button_title')">{{ __('invoicing.einvoice.button') }}</x-icon-btn>
                    <x-icon-btn icon="receipt" size="sm" :href="route('invoices.zugferd', $invoice)" show-label
                                :title="__('invoicing.einvoice.zugferd.button_title')">{{ __('invoicing.einvoice.zugferd.button') }}</x-icon-btn>
                @endif
                @can('send', $invoice)
                    <x-icon-btn icon="mail" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('invoices.send.form', $invoice)"
                                show-label>{{ __('Per E-Mail senden') }}</x-icon-btn>
                @endcan
                @can('update', $invoice)
                    @if ($invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                        <x-icon-btn icon="data_object" tone="info" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('invoices.einvoice-options.edit', $invoice)"
                                    show-label>{{ __('invoice-import.options_action') }}</x-icon-btn>
                        <x-icon-btn icon="add" tone="primary" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('invoices.items.create', $invoice)"
                                    show-label>{{ __('Position hinzufügen') }}</x-icon-btn>
                        <x-icon-btn icon="receipt_long" tone="info" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('invoices.expenses.form', $invoice)"
                                    show-label>{{ __('Spesen hinzufügen') }}</x-icon-btn>
                        {{-- MVP-416: Belegrabatt + Skonto am Entwurf --}}
                        <x-icon-btn icon="percent" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('invoices.conditions.form', $invoice)"
                                    show-label>{{ __('Konditionen') }}</x-icon-btn>
                    @endif
                @endcan
                @if (! $invoice->isProforma() && ! app(\App\Services\Finance\BillingModeResolver::class)->effectiveFor($invoice->customer)->isExternal())
                    <x-icon-btn icon="rule" size="sm" :href="route('invoices.einvoice-validation', $invoice)" show-label
                                :title="__('Preflight, XSD und KoSIT vor der Ausstellung prüfen')">{{ __('E-Rechnungs-Prüfung') }}</x-icon-btn>
                @endif
                @can('issue', $invoice)
                    @if (! $invoice->isProforma())
                        @if ($invoice->approved_at === null)
                            <x-action-form :action="route('invoices.approve', $invoice)">
                                <x-icon-btn icon="verified" tone="info" size="sm" type="submit" show-label
                                            :title="__('Fachliche Freigabe vor der Ausstellung (Vier-Augen-Option)')">{{ __('Freigeben') }}</x-icon-btn>
                            </x-action-form>
                        @endif
                        <x-action-form :action="route('invoices.issue', $invoice)">
                            <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Stellen') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    {{-- Plugin-Slot: jedes aktive Plugin kann hier eigene Aktionen (z. B. "An Lexoffice senden") einklinken --}}
                    {!! app(\App\Plugins\PluginManager::class)->renderSlot('invoice-show.actions', $invoice) !!}
                @endcan
                @if ($invoice->isProforma())
                    @can('create', \App\Models\Invoice::class)
                        <x-action-form :action="route('invoices.proforma-convert', $invoice)"
                              :confirm="__('Pro-forma :nr in eine echte Rechnung mit neuer Rechnungsnummer umwandeln?', ['nr' => $invoice->number])"
                              confirm-icon="swap_horiz"
                              confirm-tone="primary"
                              :confirm-label="__('Umwandeln')">
                            <x-icon-btn icon="swap_horiz" tone="primary" size="sm" type="submit" show-label>{{ __('In Rechnung umwandeln') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                @endif
                @if ($invoice->isOverdue() && (int) $invoice->dunning_level < 3 && (auth()->user()?->canManageBilling() ?? false))
                    <x-icon-btn icon="notification_important" tone="warning" size="sm"
                                data-entry-modal-trigger
                                :href="route('invoices.dun.form', $invoice)"
                                show-label>{{ __('Mahnen') }}</x-icon-btn>
                @endif
                @can('pay', $invoice)
                    <x-action-form :action="route('invoices.pay', $invoice)">
                        <x-icon-btn icon="check_circle" tone="success" size="sm" type="submit" show-label>{{ __('Bezahlt markieren') }}</x-icon-btn>
                    </x-action-form>
                @endcan
                @can('cancel', $invoice)
                    <x-action-form :action="route('invoices.cancel', $invoice)"
                          :confirm="__('Rechnung wirklich stornieren?')"
                          confirm-icon="block"
                          confirm-tone="warning"
                          :confirm-label="__('Stornieren')">
                        <x-icon-btn icon="block" tone="warning" size="sm" type="submit" show-label>{{ __('Stornieren') }}</x-icon-btn>
                    </x-action-form>
                @endcan
                @can('createCreditNote', $invoice)
                    <x-action-form :action="route('invoices.credit-note', $invoice)"
                          :confirm="__('Korrekturrechnung (Gutschrift) zu :nr erstellen?', ['nr' => $invoice->number])"
                          confirm-icon="undo"
                          confirm-tone="warning"
                          :confirm-label="__('Korrekturrechnung erstellen')">
                        <x-icon-btn icon="undo" tone="warning" size="sm" type="submit" show-label>{{ __('Korrekturrechnung') }}</x-icon-btn>
                    </x-action-form>
                @endcan
                @can('update', $invoice)
                    @if (($openDownPaymentCount ?? 0) > 0 && $invoice->type === \App\Models\Invoice::TYPE_INVOICE)
                        <x-action-form :action="route('invoices.final', $invoice)"
                              :confirm="__('Offene Abschlagsrechnungen (:n) anrechnen und Entwurf :nr zur Schlussrechnung machen?', ['n' => $openDownPaymentCount, 'nr' => $invoice->number])"
                              confirm-icon="functions"
                              confirm-tone="primary"
                              :confirm-label="__('Schlussrechnung erstellen')">
                            <x-icon-btn icon="functions" tone="primary" size="sm" type="submit" show-label>{{ __('Zur Schlussrechnung (:n Abschläge)', ['n' => $openDownPaymentCount]) }}</x-icon-btn>
                        </x-action-form>
                    @endif
                @endcan
                @can('delete', $invoice)
                    <x-action-form :action="route('invoices.destroy', $invoice)" method="DELETE"
                          :confirm="__('Wirklich löschen?')"
                          confirm-icon="delete"
                          confirm-tone="error"
                          :confirm-label="__('Löschen')">
                        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
                    </x-action-form>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @php $showServiceDates = $invoice->hasServicePeriod(); $footColspan = $showServiceDates ? 5 : 4; @endphp

    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="text-base-content/60">{{ __('invoice-import.preferred_format') }}</span>
        <x-status-badge :label="$invoice->delivery_format->label()" tone="info" size="xs" />
        <span class="text-xs text-base-content/50">{{ __('invoice-import.flexibility_hint') }}</span>
    </div>

    {{-- KI-Leistungstexte (Feature 084): Vorschläge nur im Entwurf, nie stille Änderungen. --}}
    @php
        $aiViewData = app(\App\Services\Ai\Suggestions\SuggestionViewData::class);
        $aiDraft = $invoice->status === \App\Models\Invoice::STATUS_DRAFT && auth()->user()?->can('update', $invoice);
        $aiSuggestEnabled = $aiDraft && $aiViewData->capabilityUsable(\App\Services\Ai\Suggestions\ItemTextSuggestionService::CAPABILITY_ITEM);
        $aiTranslateEnabled = $aiDraft && $aiViewData->capabilityUsable(\App\Services\Ai\Suggestions\ItemTextSuggestionService::CAPABILITY_TRANSLATE);
        $aiSuggestions = ($aiSuggestEnabled || $aiTranslateEnabled)
            ? $aiViewData->openSuggestionsFor((new \App\Models\InvoiceItem)->getMorphClass(), $invoice->items)
            : collect();
    @endphp
    @include('ai._learn_prompt')
    @include('invoicing._text_correction_learn')
    @if ($aiSuggestEnabled && $invoice->items->isNotEmpty())
        <div class="flex justify-end">
            <x-action-form :action="route('ai.suggestions.invoice-all', $invoice)">
                <x-icon-btn icon="auto_awesome" tone="info" size="sm" type="submit" show-label
                            :title="__('ai.suggestion.suggest_all_title')">{{ __('ai.suggestion.suggest_all') }}</x-icon-btn>
            </x-action-form>
        </div>
    @endif

    <x-table table-sort="client">
        <x-slot:head>
            <tr>
                <th>#</th>
                <x-table.th sort>{{ __('Beschreibung') }}</x-table.th>
                @if ($showServiceDates)<x-table.th sort type="date">{{ $invoice->dateLabelSingle() }}</x-table.th>@endif
                <x-table.th sort type="number" align="right">{{ __('Menge') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Einzelpreis') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Betrag') }}</x-table.th>
                @can('update', $invoice)
                    @if ($invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    @endif
                @endcan
            </tr>
        </x-slot:head>
        <x-slot:foot>
            @php $docDiscount = $invoice->documentDiscountTotal(); @endphp
            @if (!$docDiscount->isZero())
                {{-- MVP-416: Positionssumme, Belegrabatt, Netto getrennt ausweisen. --}}
                <tr><td colspan="{{ $footColspan }}" class="text-right">{{ __('Zwischensumme') }}</td><td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($invoice->lineSubtotal()->toFloat(), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
                <tr><td colspan="{{ $footColspan }}" class="text-right">{{ __('Rabatt') }}@if ($invoice->discount_percent !== null) ({{ rtrim(rtrim($invoice->discount_percent?->getNumericValue() ?? '0', '0'), '.') }}%)@endif</td><td class="text-right">−{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(abs($docDiscount->toFloat()), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
                <tr><td colspan="{{ $footColspan }}" class="text-right">{{ __('Netto') }}</td><td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($invoice->subtotal?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
            @else
                <tr><td colspan="{{ $footColspan }}" class="text-right">{{ __('Zwischensumme') }}</td><td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($invoice->subtotal?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
            @endif
            <tr><td colspan="{{ $footColspan }}" class="text-right">{{ __('USt.') }} {{ rtrim(rtrim($invoice->tax_rate?->getNumericValue() ?? '0', '0'), '.') }}%</td><td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($invoice->tax_amount?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
            @if ($invoice->is_reverse_charge)
                <tr><td colspan="{{ $footColspan + 1 }}" class="text-right text-xs text-base-content/60">{{ __('Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge).') }}</td></tr>
            @endif
            <tr><td colspan="{{ $footColspan }}" class="text-right font-bold">{{ __('Gesamt') }}</td><td class="text-right font-bold">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($invoice->total?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
            @if ($invoice->hasSkonto())
                <tr><td colspan="{{ $footColspan + 1 }}" class="text-right text-xs text-base-content/60">
                    {{ __(':percent % Skonto bei Zahlung innerhalb von :days Tagen', ['percent' => rtrim(rtrim($invoice->skonto_percent?->getNumericValue() ?? '0', '0'), '.'), 'days' => (int) $invoice->skonto_days]) }}@if ($invoice->skontoDeadline() !== null) ({{ __('bis :date', ['date' => $invoice->skontoDeadline()->fdate()]) }} = {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($invoice->total?->toFloat() ?? 0.0) - $invoice->skontoAmount()->toFloat(), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }})@endif
                </td></tr>
            @endif
        </x-slot:foot>
        @forelse ($invoice->items as $item)
            <tr>
                <td>{{ $item->position }}</td>
                <td>{{ $item->description }}</td>
                @if ($showServiceDates)<td data-sort-value="{{ optional($item->service_date)->toDateString() }}">{{ optional($item->service_date)->fdate() ?: '—' }}</td>@endif
                <td class="text-right" data-sort-value="{{ (float) $item->quantity }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->quantity, ((int) round((float) $item->quantity * 1000)) % 10 !== 0 ? 3 : 2, withThousandsSeparator: true) }} {{ $item->unit }}@if ($item->unit === __('invoicing.unit_hour')) <span class="whitespace-nowrap text-xs text-base-content/60">({{ \App\Support\Formats::duration((int) round((float) $item->quantity * 60), 'clock') }})</span>@endif</td>
                <td class="text-right" data-sort-value="{{ ($item->unit_price?->toFloat() ?? 0.0) }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($item->unit_price?->toFloat() ?? 0.0), ((int) round(($item->unit_price?->toFloat() ?? 0.0) * 10000)) % 100 !== 0 ? 4 : 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td>
                <td class="text-right" data-sort-value="{{ ($item->amount?->toFloat() ?? 0.0) }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($item->amount?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td>
                @can('update', $invoice)
                    @if ($invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                        <td class="text-right whitespace-nowrap">
                            @if ($aiSuggestEnabled)
                                <x-action-form :action="route('ai.suggestions.invoice-item', [$invoice, $item])">
                                    <x-icon-btn icon="auto_awesome" size="xs" tone="info" type="submit" :title="__('ai.suggestion.suggest')" />
                                </x-action-form>
                            @endif
                            @if ($aiTranslateEnabled)
                                <x-icon-btn icon="translate" size="xs" tone="ghost"
                                            data-entry-modal-trigger
                                            :href="route('ai.suggestions.invoice-item-translate-form', [$invoice, $item])"
                                            :title="__('ai.suggestion.translate')" />
                            @endif
                            <x-icon-btn icon="edit" size="xs" tone="ghost"
                                        data-entry-modal-trigger
                                        :href="route('invoices.items.edit', [$invoice, $item])"
                                        :title="__('Bearbeiten')" />
                            <x-action-form :action="route('invoices.items.destroy', [$invoice, $item])" method="DELETE"
                                  :confirm="__('Position wirklich entfernen?')"
                                  confirm-icon="delete"
                                  confirm-tone="error"
                                  :confirm-label="__('Entfernen')">
                                <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                            </x-action-form>
                        </td>
                    @endif
                @endcan
            </tr>
            @if ($item->timeEntries->isNotEmpty())
                {{-- Quell-Zeiten der Position (MVP-462): Herkunft je Block sichtbar machen. --}}
                <tr>
                    <td colspan="{{ $footColspan + 2 }}" class="py-1">
                        <details>
                            <summary class="cursor-pointer text-xs text-base-content/60">
                                {{ trans_choice('invoicing.source_times', $item->timeEntries->count(), ['count' => $item->timeEntries->count()]) }}
                            </summary>
                            <ul class="mt-1 space-y-0.5 pl-4">
                                @foreach ($item->timeEntries as $sourceEntry)
                                    <li class="flex flex-wrap items-center gap-2 text-xs text-base-content/70">
                                        <span class="whitespace-nowrap">{{ $sourceEntry->date?->format(\App\Support\Formats::date()) ?? '—' }}</span>
                                        <span>{{ $sourceEntry->user->name ?? '—' }}</span>
                                        <span class="max-w-md truncate" title="{{ $sourceEntry->description }}">{{ $sourceEntry->description }}</span>
                                        <x-duration :minutes="$sourceEntry->minutes" class="ml-auto" />
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    </td>
                </tr>
            @endif
            @if ($aiDraft && ($aiSuggestions[$item->id] ?? null) !== null)
                <tr data-ai-suggestion-row>
                    <td colspan="{{ $footColspan + 2 }}">
                        <x-ai-suggestion
                            :original="$aiSuggestions[$item->id]->original"
                            :suggestion="$aiSuggestions[$item->id]->suggestion"
                            :provider="$aiSuggestions[$item->id]->provider"
                            :fallback="$aiSuggestions[$item->id]->fallback_used"
                            :cached="$aiSuggestions[$item->id]->from_cache"
                            :accept-action="route('ai.suggestions.accept', $aiSuggestions[$item->id])"
                            :reject-action="route('ai.suggestions.reject', $aiSuggestions[$item->id])"
                            field-name="text"
                        />
                    </td>
                </tr>
            @endif
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>' :colspan="5" :title="__('Keine Positionen.')" compact />
        @endforelse
    </x-table>

    <div class="grid gap-2 text-sm text-base-content/70 sm:grid-cols-3">
        <div>{{ __('Zahlungsziel: :days Tage', ['days' => (int) ($invoice->payment_terms_days ?? 14) ]) }}@if ($invoice->due_on) · {{ __('fällig am :date', ['date' => $invoice->due_on->fdate()]) }}@endif</div>
        @if ($invoice->approved_at)
            <div>{{ __('Freigegeben am :date', ['date' => $invoice->approved_at->fdatetime()]) }}</div>
        @endif
    </div>

    {{-- Zustellnachweis (MVP-168): jeder Versand/Download ist ein eigener Versuch --}}
    @php $dispatches = $invoice->dispatches()->with([])->limit(50)->get(); @endphp
    @if ($dispatches->isNotEmpty())
        <details class="collapse collapse-arrow border border-base-300 bg-base-100">
            <summary class="collapse-title text-sm font-medium">{{ __('Zustellversuche (:count)', ['count' => $dispatches->count()]) }}</summary>
            <div class="collapse-content overflow-x-auto">
                <x-table>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Zeitpunkt') }}</th>
                            <th>{{ __('Kanal') }}</th>
                            <th>{{ __('Format') }}</th>
                            <th>{{ __('Empfänger') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('SHA-256') }}</th>
                        </tr>
                    </x-slot:head>
                        @foreach ($dispatches as $dispatch)
                            <tr>
                                <td>{{ $dispatch->created_at->fdatetime() }}</td>
                                <td>{{ __($dispatch->channel) }}</td>
                                <td>{{ $dispatch->format ?? '—' }}</td>
                                <td class="max-w-xs truncate">{{ $dispatch->recipient ?? '—' }}</td>
                                <td>{{ __($dispatch->status) }}</td>
                                <td class="font-mono text-xs">{{ $dispatch->sha256 !== null ? substr($dispatch->sha256, 0, 16) . '…' : '—' }}</td>
                            </tr>
                        @endforeach
                </x-table>
            </div>
        </details>
    @endif
</x-page-shell>
@endsection
