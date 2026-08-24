<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceIssueService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Services\Invoicing\EInvoice\EInvoiceValidationService;

/**
 * Einzige Schreibstelle für den Übergang draft → issued (Vollscan 2026-08-23,
 * B1): Vorher existierte die Ausstellung dreifach — Controller (mit
 * Pro-forma-Guard, Freigabepflicht, Partei-Snapshot, Fälligkeit und
 * tax_context-Freeze), Invoice::markSent() (ohne tax_context und ohne
 * Freigabepflicht) und der Lexoffice-Push (setzte nur den Status). Eine per
 * Mail oder Lexoffice ausgestellte Rechnung hatte damit keinen eingefrorenen
 * Steuerkontext (MVP-243) und umging invoicing.require_approval.
 */
final class InvoiceIssueService {
    public function __construct(
        private readonly TaxResolver $taxResolver,
        private readonly EInvoiceValidationService $eInvoiceValidation,
    ) {}

    /** Würde dieser Beleg beim Ausstellen den Rechnungsstatus bekommen? */
    public function wouldIssue(Invoice $invoice): bool {
        return $invoice->status === Invoice::STATUS_DRAFT && ! $invoice->isCreditNote() && ! $invoice->isProforma();
    }

    /**
     * Fachliche Voraussetzungen der Ausstellung: keine Pro-forma, Freigabe
     * (MVP-163, Opt-in), E-Rechnungs-Validierung (MVP-164, Opt-in).
     *
     * @throws InvoiceIssueException
     */
    public function assertIssuable(Invoice $invoice): void {
        if ($invoice->isProforma()) {
            throw new InvoiceIssueException(InvoiceIssueException::REASON_PROFORMA, (string) __('Eine Pro-forma-Rechnung wird nicht gestellt — wandeln Sie sie in eine echte Rechnung um.'));
        }

        $invoicingSettings = (array) data_get($invoice->organization?->settings, 'invoicing', []);
        if ((string) ($invoicingSettings['require_approval'] ?? '0') === '1' && $invoice->approved_at === null) {
            throw new InvoiceIssueException(InvoiceIssueException::REASON_APPROVAL_MISSING, (string) __('Die Rechnung braucht vor der Ausstellung eine Freigabe.'));
        }

        $einvoiceSettings = (array) data_get($invoice->organization?->settings, 'einvoice', []);
        if ((string) ($einvoiceSettings['enforce_validation'] ?? '0') === '1') {
            $report = $this->eInvoiceValidation->validate($invoice);
            if (! $report['valid'] || $report['preflight_errors'] !== []) {
                throw new InvoiceIssueException(InvoiceIssueException::REASON_EINVOICE_INVALID, (string) __('Die Rechnung besteht die E-Rechnungs-Validierung nicht — Ausstellung abgebrochen.'));
            }
        }
    }

    /**
     * Übergang draft → issued: Partei-/Layout-Snapshot (MVP-162), Fälligkeit,
     * eingefrorener Steuerkontext (MVP-243). Idempotent — ein bereits
     * ausgestellter Beleg bleibt unangetastet. Zusätzliche Spalten (z. B. die
     * von Lexoffice vergebene Nummer) werden mit demselben Save geschrieben,
     * bevor der Unveränderlichkeits-Guard greift.
     *
     * @param  array<string, mixed>  $extra
     */
    public function issue(Invoice $invoice, array $extra = []): Invoice {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            return $invoice;
        }

        $invoice->loadMissing(['items', 'customer', 'organization']);
        $organization = $invoice->organization;
        $resolvedOn = $invoice->serviceDateTo() ?? now();
        $taxResolution = $organization !== null
            ? $this->taxResolver->resolve($organization, $invoice->customer, $resolvedOn)
            : null;

        $fromFile = $invoice->number_source === 'file_import';

        $invoice->freezeParties();
        $invoice->update($extra + [
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => $fromFile && $invoice->issued_on !== null ? $invoice->issued_on : ($invoice->issued_on ?? now()),
            'due_on' => $fromFile && $invoice->due_on !== null
                ? $invoice->due_on
                : ($invoice->due_on ?? now()->addDays($invoice->payment_terms_days ?? 14)),
            'tax_context' => [
                'resolved_on' => $resolvedOn->toDateString(),
                'rate' => $invoice->tax_rate?->getNumericValue() ?? '',
                'is_reverse_charge' => (bool) $invoice->is_reverse_charge,
                'breakdown' => $invoice->tax_breakdown,
                'category' => $taxResolution['category'] ?? null,
                'rule' => $taxResolution['rule'] ?? null,
                'item_categories' => $invoice->items->pluck('tax_category', 'id')->all(),
            ],
        ]);

        return $invoice;
    }
}
