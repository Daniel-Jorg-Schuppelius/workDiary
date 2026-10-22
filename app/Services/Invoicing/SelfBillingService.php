<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfBillingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind, SelfBillingStatus};
use App\Enums\Numbering\NumberScope;
use App\Exceptions\IncomingInvoiceDecisionException;
use App\Models\Invoicing\{SelfBillingCredit, SelfBillingCreditLine};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingInvoiceMatcher, SelfBillingXmlGenerator};
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gutschriftsverfahren (MVP-1280). Ausstellen vergibt die Nummer aus dem
 * eigenen Kreis, friert die Summen ein und reicht die XRechnung (Typcode 389)
 * an den Rechnungseingang weiter: Für die Buchhaltung ist die Gutschrift eine
 * Eingangsleistung, und der Eingang führt die Übergabe an DATEV und die
 * übrigen Programme bereits — es gibt keinen zweiten Buchungsweg.
 */
class SelfBillingService {
    public function __construct(
        private readonly SelfBillingXmlGenerator $xml,
        private readonly NumberSequenceService $numbers,
    ) {}

    /** @param array{service_from?: ?string, service_until?: ?string, note?: ?string} $data */
    public function createDraft(Supplier $supplier, User $actor, array $data): SelfBillingCredit {
        return SelfBillingCredit::query()->create([
            'organization_id' => $supplier->organization_id,
            'supplier_id' => $supplier->id,
            'currency' => $supplier->currency->value,
            'service_from' => $data['service_from'] ?? null,
            'service_until' => $data['service_until'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    /** @param array{description: string, quantity: string|float|int, unit?: ?string, unit_price: string|float|int, tax_rate?: string|float|int|null} $data */
    public function addLine(SelfBillingCredit $credit, array $data): SelfBillingCreditLine {
        $this->guardDraft($credit);

        return $credit->lines()->create([
            'organization_id' => $credit->organization_id,
            'position' => (int) $credit->lines()->max('position') + 1,
            'description' => $data['description'],
            'quantity' => (string) $data['quantity'],
            'unit' => $data['unit'] ?? null,
            'unit_price' => (string) $data['unit_price'],
            'tax_rate' => isset($data['tax_rate']) ? (string) $data['tax_rate'] : null,
        ]);
    }

    public function removeLine(SelfBillingCredit $credit, SelfBillingCreditLine $line): void {
        $this->guardDraft($credit);
        abort_unless((int) $line->self_billing_credit_id === (int) $credit->id, 404);
        $line->delete();
    }

    public function issue(SelfBillingCredit $credit, User $actor, ?Carbon $on = null): SelfBillingCredit {
        $errors = $this->xml->preflight($credit);
        if ($errors !== []) {
            throw ValidationException::withMessages(['self_billing' => $errors]);
        }

        $credit = DB::transaction(function () use ($credit, $actor, $on): SelfBillingCredit {
            $locked = SelfBillingCredit::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->canTransitionTo(SelfBillingStatus::Issued)) {
                throw ValidationException::withMessages(['self_billing' => (string) __('invoicing.self_billing.errors.locked')]);
            }
            $issuedOn = $on ?? Carbon::today();
            $totals = $locked->documentTotals();
            $locked->update([
                'number' => $this->numbers->next((int) $locked->organization_id, NumberScope::SelfBilling, $issuedOn),
                'status' => SelfBillingStatus::Issued->value,
                'issued_on' => $issuedOn->toDateString(),
                'net_amount' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'gross_amount' => $totals['total'],
                'issuer_user_id' => $actor->id,
                'issued_at' => Carbon::now(),
            ]);
            $locked->audit('self_billing_credit.issued', ['number' => $locked->number, 'gross_amount' => $locked->gross_amount?->getAmount()]);

            return $locked->refresh();
        });

        $this->handOverToIncoming($credit, $actor);

        return $credit->refresh();
    }

    /**
     * Storno nur, solange die Buchhaltung den Beleg nicht übernommen hat —
     * danach ist die Korrektur eine neue Gutschrift mit negativen Beträgen.
     */
    public function cancel(SelfBillingCredit $credit, User $actor, string $reason): SelfBillingCredit {
        return DB::transaction(function () use ($credit, $actor, $reason): SelfBillingCredit {
            $locked = SelfBillingCredit::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->canTransitionTo(SelfBillingStatus::Cancelled)) {
                throw ValidationException::withMessages(['self_billing' => (string) __('invoicing.self_billing.errors.not_cancellable')]);
            }
            $incoming = $locked->incoming;
            if ($incoming?->transferred_at !== null) {
                throw ValidationException::withMessages(['self_billing' => (string) __('invoicing.self_billing.errors.transferred')]);
            }
            $locked->update([
                'status' => SelfBillingStatus::Cancelled->value,
                'canceller_user_id' => $actor->id,
                'cancelled_at' => Carbon::now(),
                'cancel_reason' => trim($reason),
            ]);
            $locked->audit('self_billing_credit.cancelled', ['reason' => trim($reason)]);
            // Der Eingang wird abgelehnt, nicht gelöscht: er bleibt mit dem Grund im Prüfbereich nachvollziehbar.
            if ($incoming !== null && $incoming->status !== IncomingEInvoiceStatus::Rejected) {
                try {
                    app(IncomingEInvoiceService::class)->decide($incoming, IncomingEInvoiceStatus::Rejected, $actor, (string) __('invoicing.self_billing.discard_reason', ['number' => $locked->number]));
                } catch (IncomingInvoiceDecisionException $e) {
                    throw ValidationException::withMessages(['self_billing' => $e->getMessage()]);
                }
            }

            return $locked->refresh();
        });
    }

    /** Eingang anlegen und dem Lieferanten zuordnen; ein Fehler hier nimmt das Ausstellen nicht zurück. */
    private function handOverToIncoming(SelfBillingCredit $credit, User $actor): void {
        $result = app(IncomingEInvoiceService::class)->storeIncoming(
            $actor,
            $this->xml->generate($credit),
            'application/xml',
            null,
            'self_billing',
            null,
            $credit->number . '.xml',
        );
        $incoming = $result['incoming'];
        if ($incoming === null) {
            return;
        }
        if ($incoming->supplier_id === null) {
            app(IncomingInvoiceMatcher::class)->assign($incoming, $credit->supplier()->firstOrFail(), IncomingInvoiceMatchKind::Manual, $actor);
        }
        $credit->update(['incoming_e_invoice_id' => $incoming->id]);
    }

    private function guardDraft(SelfBillingCredit $credit): void {
        if (! $credit->isDraft()) {
            throw ValidationException::withMessages(['self_billing' => (string) __('invoicing.self_billing.errors.locked')]);
        }
    }
}
