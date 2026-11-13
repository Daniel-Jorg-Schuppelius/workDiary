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

use App\Enums\Invoicing\{InvoiceStatus, TaxCaseAlignment};
use App\Models\Invoicing\{Invoice, OnlinePayment};
use App\Models\Platform\Organization;
use App\Services\Integration\LifecycleWebhookPublisher;
use App\Services\Invoicing\EInvoice\EInvoiceValidationService;
use App\Services\Numbering\{DocumentIssuers, NumberAuthority, NumberSequenceService};
use App\Support\ErrorText;
use CommonToolkit\ValueObjects\Percentage;
use Illuminate\Support\Facades\Cache;

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
        // Sperre nehmen, unter der Sperre neu prüfen (Sicherheitsaudit 2026-10-21, li-1): ein zweiter Aufruf
        // wartet und sieht den geschriebenen Stand. Bewusst keine DB-Transaktion — vergibt ein Programm die
        // Nummer, muss die Verknüpfung zum dort angelegten Beleg einen Fehlschlag überleben.
        return Cache::lock('document-issue:' . $invoice->getMorphClass() . ':' . $invoice->getKey(), 120)
            ->block(30, function () use ($invoice, $extra): Invoice {
                $invoice->refresh();

                return $this->issueLocked($invoice, $extra);
            });
    }

    /** @param  array<string, mixed>  $extra */
    private function issueLocked(Invoice $invoice, array $extra = []): Invoice {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $invoice;
        }

        $invoice->loadMissing(['items', 'customer', 'organization']);
        if ($invoice->customer->is_collective) {
            throw new InvoiceIssueException(InvoiceIssueException::REASON_COLLECTIVE_CUSTOMER, (string) __('An den Sammelkunden lässt sich keine Rechnung stellen. Bitte einen Kunden mit Anschrift wählen.'));
        }
        // Hat sich der Steuerfall seit der letzten Bearbeitung geändert, geht der angepasste Entwurf erst nach Prüfung hinaus.
        $alignment = $this->alignTaxCase($invoice);
        if ($alignment->changed()) {
            $invoice->recalculate();
            $invoice->save();

            throw new InvoiceIssueException(InvoiceIssueException::REASON_TAX_CASE_CHANGED, (string) __('invoicing.tax_case.changed_on_issue')
                . ($alignment === TaxCaseAlignment::NoteKept ? ' ' . __('invoicing.tax_case.note_kept') : ''));
        }
        if ($invoice->pricedItems()->isEmpty()) {
            throw new InvoiceIssueException(InvoiceIssueException::REASON_EMPTY, (string) __('invoicing.free.error.empty'));
        }
        $organization = $invoice->organization;
        $resolvedOn = $invoice->serviceDateTo() ?? now();
        $taxResolution = $organization !== null
            ? $this->taxResolver->resolve($organization, $invoice->customer, $resolvedOn, $invoice->isMaterial() ? 'goods' : 'services')
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
            // § 35a-Ausweis festschreiben: Organisationsregel und Kundenart dürfen den Nachdruck nicht ändern (MVP-1624).
            'is_labour_cost_disclosed' => $invoice->is_labour_cost_disclosed ?? $invoice->disclosesLabourCosts(),
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

        // Rechnungsbegründende Unterlagen (Feature 196): mit der endgültigen Nummer erzeugen und einfrieren.
        app(\App\Services\Invoicing\EInvoice\SupportingDocumentService::class)->prepareForIssue($invoice, auth()->user() instanceof \App\Models\Platform\User ? auth()->user() : null);

        // Feature 160 (MVP-858): reservierte Fertigungsauslieferungen gelten als abgerechnet.
        app(DeliveryInvoicingService::class)->markInvoiced($invoice);

        // Eine dem Entwurf zugeordnete Vorauszahlung (MVP-1515) deckt die Rechnung ab der Ausstellung.
        if (OnlinePayment::settledSumFor($invoice) > 0) {
            app(InvoiceSettlement::class)->sync($invoice, now()->startOfDay());
        }

        // Lifecycle-Webhook (MVP-718): invoice.issued an der Service-Schreibstelle.
        $this->lifecycleWebhooks->invoiceIssued($invoice);

        \App\Events\Invoicing\InvoiceIssued::dispatch((int) $invoice->organization_id, (int) $invoice->id);

        return $invoice;
    }

    /**
     * Steuerfall des Entwurfs nach den aktuellen Kunden- und Organisationsdaten
     * (MVP-1623): Weicht er ab, kommen Reverse Charge und Kopfsatz wie bei
     * generierten Rechnungen aus dem {@see TaxResolver} — etwa wenn der Kunde
     * nach der Anlage zum Leistungsempfänger nach § 13b wurde, eine USt-IdNr.
     * bekam oder ins Drittland zog.
     * Setzt nur die Felder; Summen rechnet und speichert der Aufrufer.
     * Gutschriften, Stornos und importierte Belege tragen den Fall ihres
     * Originals bzw. ihrer Datei.
     */
    public function alignTaxCase(Invoice $invoice): TaxCaseAlignment {
        if ($invoice->status !== InvoiceStatus::Draft || $invoice->isCreditNote() || $invoice->type === Invoice::TYPE_CANCELLATION
            || in_array($invoice->number_source, ['file_import', \App\Services\Invoicing\Import\InvoiceSpec::NUMBER_SOURCE], true)) {
            return TaxCaseAlignment::Unchanged;
        }
        $organization = $invoice->organization;
        if ($organization === null) {
            return TaxCaseAlignment::Unchanged;
        }

        $tax = $this->taxResolver->resolve($organization, $invoice->customer, $invoice->serviceDateTo() ?? now(), $invoice->isMaterial() ? 'goods' : 'services');
        $rate = Percentage::of((string) $tax['rate'], 2);
        // Der Fall zählt, nicht der Satz: Reverse Charge oder steuerfrei gegen steuerpflichtig. Ein anderer Regelsatz bleibt Sache des Entwurfs.
        if ((bool) $invoice->is_reverse_charge === $tax['reverse_charge'] && $rate->isZero() === ($invoice->tax_rate?->isZero() ?? true)) {
            return TaxCaseAlignment::Unchanged;
        }
        // Reverse Charge und 0 % tragen einen Pflichthinweis (§ 14a UStG, Steuerbefreiung, § 19); der Regelsatz keinen.
        $hadTaxNote = (bool) $invoice->is_reverse_charge || ($invoice->tax_rate?->isZero() ?? true);
        $invoice->is_reverse_charge = $tax['reverse_charge'];
        $invoice->tax_rate = $rate;

        return $this->alignTaxNote($invoice, $organization, trim((string) $tax['note']), $hadTaxNote);
    }

    /**
     * Hinweistext des neuen Steuerfalls: Ein unverändert stehender automatischer
     * Text des alten Falls wird ersetzt bzw. entfällt. Fehlt er, obwohl der alte
     * Fall einen trug, hat ihn jemand geändert — dann bleibt der Text stehen.
     */
    private function alignTaxNote(Invoice $invoice, Organization $organization, string $note, bool $hadTaxNote): TaxCaseAlignment {
        $notes = (string) $invoice->notes;
        $stale = array_values(array_filter(
            app(TaxExemptionNotes::class)->automatic($organization),
            static fn (string $automatic): bool => $automatic !== $note && str_contains($notes, $automatic),
        ));
        $hasNote = $note !== '' && str_contains($notes, $note);

        if ($stale === []) {
            if ($hasNote) {
                return TaxCaseAlignment::Aligned;
            }
            if ($hadTaxNote && trim($notes) !== '') {
                return TaxCaseAlignment::NoteKept;
            }
            $notes = trim($notes . "\n" . $note);
        } else {
            foreach ($stale as $i => $automatic) {
                $notes = $i === 0 && ! $hasNote && $note !== ''
                    ? str_replace($automatic, $note, $notes)
                    : str_replace([$automatic . "\n", "\n" . $automatic, $automatic], '', $notes);
            }
        }
        $notes = trim((string) preg_replace("/\n{3,}/", "\n\n", $notes));
        $invoice->notes = $notes !== '' ? $notes : null;

        return TaxCaseAlignment::Aligned;
    }

    /** Programm, das die Nummer dieses Belegs vergibt; null = workDiary. */
    private function issuingProgram(Invoice $invoice): ?string {
        $authority = app(NumberAuthority::class);
        $source = $authority->sourceFor((int) $invoice->organization_id, $invoice->numberScope());

        return $source === NumberAuthority::SOURCE_LOCAL ? null : $authority->sourceLabel($source);
    }
}
