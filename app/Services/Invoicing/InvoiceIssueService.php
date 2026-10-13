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

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Invoicing\Invoice;
use App\Services\Integration\LifecycleWebhookPublisher;
use App\Services\Invoicing\EInvoice\EInvoiceValidationService;
use App\Services\Numbering\{DocumentIssuers, NumberAuthority, NumberSequenceService};
use App\Support\ErrorText;

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
        private readonly LifecycleWebhookPublisher $lifecycleWebhooks,
    ) {}

    /** Würde dieser Beleg beim Ausstellen den Rechnungsstatus bekommen? */
    public function wouldIssue(Invoice $invoice): bool {
        return $invoice->status === InvoiceStatus::Draft && ! $invoice->isCreditNote() && ! $invoice->isProforma();
    }

    /**
     * Fachliche Voraussetzungen der Ausstellung: keine Pro-forma, Freigabe
     * (MVP-163, Opt-in), E-Rechnungs-Validierung (MVP-164, Opt-in). Eine
     * Entwurfsnummer stellt nur die Übertragung an das vergebende Programm aus
     * (`$numberFromProgram`, MVP-1153).
     *
     * @throws InvoiceIssueException
     */
    public function assertIssuable(Invoice $invoice, bool $numberFromProgram = false): void {
        if ($invoice->isProforma()) {
            throw new InvoiceIssueException(InvoiceIssueException::REASON_PROFORMA, (string) __('Eine Pro-forma-Rechnung wird nicht gestellt — wandeln Sie sie in eine echte Rechnung um.'));
        }
        // Eine Entwurfsnummer ersetzt das vergebende Programm beim Ausstellen (MVP-1166) — kann es den Beleg nicht übernehmen, bleibt er Entwurf.
        $program = $this->issuingProgram($invoice);
        if (! $numberFromProgram && $program !== null && NumberSequenceService::isDraft($invoice->number)
            && ! app(DocumentIssuers::class)->canIssue((int) $invoice->organization_id, $invoice->numberScope())) {
            throw new InvoiceIssueException(InvoiceIssueException::REASON_DRAFT_NUMBER, (string) __('numbering.issue.draft_number', ['program' => $program]));
        }
        // Feature 160 (MVP-856): ein leerer Entwurf verlässt das Haus auf keinem Weg.
        // Titel und Text allein machen keinen Beleg (MVP-1054).
        if ($invoice->items()->where('line_kind', \App\Enums\Billing\DocumentLineKind::Item->value)->doesntExist()) {
            throw new InvoiceIssueException(InvoiceIssueException::REASON_EMPTY, (string) __('invoicing.free.error.empty'));
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
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $invoice;
        }

        $invoice->loadMissing(['items', 'customer', 'organization']);
        if ($invoice->customer->is_collective) {
            throw new InvoiceIssueException(InvoiceIssueException::REASON_COLLECTIVE_CUSTOMER, (string) __('An den Sammelkunden lässt sich keine Rechnung stellen. Bitte einen Kunden mit Anschrift wählen.'));
        }
        if ($invoice->pricedItems()->isEmpty()) {
            throw new InvoiceIssueException(InvoiceIssueException::REASON_EMPTY, (string) __('invoicing.free.error.empty'));
        }
        $organization = $invoice->organization;
        $resolvedOn = $invoice->serviceDateTo() ?? now();
        $taxResolution = $organization !== null
            ? $this->taxResolver->resolve($organization, $invoice->customer, $resolvedOn)
            : null;

        $fromFile = $invoice->number_source === 'file_import';

        // Entwurfsnummer: Das vergebende Programm übernimmt den Beleg und liefert seine Nummer, sonst zieht workDiary sie (MVP-1166).
        if (! array_key_exists('number', $extra) && NumberSequenceService::isDraft($invoice->number)) {
            try {
                $extra['number'] = app(DocumentIssuers::class)->numberOnIssue($invoice, $invoice->numberScope());
            } catch (\RuntimeException $e) {
                throw new InvoiceIssueException(InvoiceIssueException::REASON_PROGRAM, (string) __('numbering.issue.transfer_failed', ['program' => (string) $this->issuingProgram($invoice), 'error' => ErrorText::for($e)]), previous: $e);
            }
        }

        $invoice->freezeParties();
        // Festgeschrieben, damit Fälligkeit und E-Rechnung dasselbe Ziel nennen (MVP-996).
        $paymentTermsDays = $invoice->effectivePaymentTermsDays();
        $invoice->update($extra + [
            'status' => InvoiceStatus::Issued,
            'payment_terms_days' => $paymentTermsDays,
            'issued_on' => $fromFile && $invoice->issued_on !== null ? $invoice->issued_on : ($invoice->issued_on ?? now()),
            'due_on' => $fromFile && $invoice->due_on !== null
                ? $invoice->due_on
                : ($invoice->due_on ?? now()->addDays($paymentTermsDays)),
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

        // Feature 160 (MVP-858): reservierte Fertigungsauslieferungen gelten als abgerechnet.
        app(DeliveryInvoicingService::class)->markInvoiced($invoice);

        // Lifecycle-Webhook (MVP-718): invoice.issued an der Service-Schreibstelle.
        $this->lifecycleWebhooks->invoiceIssued($invoice);

        return $invoice;
    }

    /** Programm, das die Nummer dieses Belegs vergibt; null = workDiary. */
    private function issuingProgram(Invoice $invoice): ?string {
        $authority = app(NumberAuthority::class);
        $source = $authority->sourceFor((int) $invoice->organization_id, $invoice->numberScope());

        return $source === NumberAuthority::SOURCE_LOCAL ? null : $authority->sourceLabel($source);
    }
}
