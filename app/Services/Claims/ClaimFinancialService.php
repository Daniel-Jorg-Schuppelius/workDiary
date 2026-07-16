<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimFinancialService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims;

use App\Enums\Claims\{ClaimFinancialKind, ClaimFinancialStatus};
use App\Enums\Finance\BillingMode;
use App\Models\Claims\{ClaimCase, ClaimFinancialOutcome};
use App\Models\User;
use App\Services\Finance\BillingModeResolver;
use App\Services\Invoicing\InvoiceGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Kaufmännische Folgen (Feature 072, MVP-252, Entscheidung D1): KEIN
 * neuer Belegtyp — die Art lebt in claim_financial_outcomes.kind; auf
 * Faktura-Seite entstehen Gutschrift/Storno über den InvoiceGenerator,
 * ergänzt um das strukturierte reason_kind am Beleg. Vier-Augen-Prinzip:
 * Vorschlagender ≠ Freigebender.
 */
class ClaimFinancialService {
    public function __construct(
        private readonly InvoiceGenerator $invoices,
        private readonly BillingModeResolver $billingModes,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function propose(ClaimCase $case, User $proposer, ClaimFinancialKind $kind, array $attributes): ClaimFinancialOutcome {
        return $case->financialOutcomes()->create(array_merge($attributes, [
            'organization_id' => $case->organization_id,
            'kind' => $kind->value,
            'status' => ClaimFinancialStatus::Proposed->value,
            'proposed_by' => $proposer->id,
        ]));
    }

    /** Vier-Augen-Freigabe (MVP-252): Selbstfreigabe ist gesperrt. */
    public function approve(ClaimFinancialOutcome $outcome, User $approver): ClaimFinancialOutcome {
        if ($outcome->status !== ClaimFinancialStatus::Proposed) {
            throw new \RuntimeException((string) __('Nur vorgeschlagene Folgen können freigegeben werden.'));
        }
        if ((int) $outcome->proposed_by === (int) $approver->id) {
            throw new \RuntimeException((string) __('Selbstfreigabe ist nicht zulässig (Vier-Augen-Prinzip).'));
        }
        $outcome->forceFill([
            'status' => ClaimFinancialStatus::Approved->value,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        return $outcome;
    }

    /**
     * Ausführung (MVP-252): erzeugt je nach Art den Faktura-Folgebeleg
     * (Gutschrift/Storno als Entwurf, reason_kind strukturiert gesetzt)
     * oder schließt ohne Beleg ab (Rückerstattung, Ersatzrechnung als
     * Verweis). Berechnet wird im Faktura-Modul, nie hier.
     */
    public function execute(ClaimFinancialOutcome $outcome, User $actor): ClaimFinancialOutcome {
        if ($outcome->status !== ClaimFinancialStatus::Approved) {
            throw new \RuntimeException((string) __('Nur freigegebene Folgen können ausgeführt werden.'));
        }

        return DB::transaction(function () use ($outcome, $actor): ClaimFinancialOutcome {
            $result = null;
            if ($outcome->kind->producesInvoice()) {
                // Beleghoheit (Feature 045): führt Lexoffice/DATEV die Rechnungen, entsteht kein lokaler
                // Korrekturbeleg — dort wird korrigiert, die Belegnummer hier nachgetragen (external_reference).
                $mode = $this->billingModeFor($outcome);
                if ($mode->isExternal()) {
                    $hint = (string) __('Beleghoheit liegt bei :system — Rechnungskorrektur dort anlegen und Belegnummer hier nachtragen.', ['system' => $mode->label()]);
                    $outcome->forceFill([
                        'status' => ClaimFinancialStatus::Executed->value,
                        'executed_at' => now(),
                        'note' => trim((string) $outcome->note . "\n" . $hint),
                    ])->save();

                    return $outcome;
                }

                $invoice = $outcome->invoice;
                if ($invoice === null) {
                    throw new \RuntimeException((string) __('Für diese Folge fehlt der Quellbeleg (Rechnung).'));
                }
                $result = $outcome->kind === ClaimFinancialKind::Cancellation
                    ? $this->invoices->cancellationFor($invoice, $outcome->justification, $actor->id)
                    : $this->invoices->creditNoteFor($invoice, $actor->id);
                // D1: strukturierter Grund am Beleg statt neuem Belegtyp.
                $result->forceFill(['reason_kind' => $outcome->kind->value])->save();
            }

            $outcome->forceFill([
                'status' => ClaimFinancialStatus::Executed->value,
                'result_invoice_id' => $result?->id,
                'executed_at' => now(),
            ])->save();

            return $outcome;
        });
    }

    /** Externe Belegnummer (führendes System) an einer ausgeführten Folge nachtragen. */
    public function recordExternalReference(ClaimFinancialOutcome $outcome, string $reference): ClaimFinancialOutcome {
        if ($outcome->status !== ClaimFinancialStatus::Executed || $outcome->result_invoice_id !== null) {
            throw new \RuntimeException((string) __('Externe Belegnummern gibt es nur für extern ausgeführte Folgen.'));
        }
        $outcome->forceFill(['external_reference' => $reference])->save();

        return $outcome;
    }

    /** Hoheits-Kaskade: Fall-Kunde, sonst Kunde des Quellbelegs, sonst lokal. */
    private function billingModeFor(ClaimFinancialOutcome $outcome): BillingMode {
        $customer = $outcome->claimCase->customer ?? $outcome->invoice->customer ?? null;

        return $customer !== null ? $this->billingModes->effectiveFor($customer) : BillingMode::Workdiary;
    }
}
