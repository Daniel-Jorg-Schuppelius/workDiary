<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerVoucherReconciler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Models\Billing\CustomerBillingAgreement;
use App\Models\{ExternalReference, Invoice, LexofficeVoucher, Organization};
use App\Plugins\Lexoffice\{LexofficeInvoiceService, LexofficePlugin};
use App\Support\Tz;
use Illuminate\Support\Carbon;

/**
 * Retainer-Zahlstatus-Rücksync (Feature 098): spiegelt den Lexoffice-Beleg-
 * status (paid/teilbezahlt/storniert) der Pauschal-/Ausgleichsbelege
 * (TYPE_RETAINER) zurück in den Leistungssaldo. Läuft nach {@see LexofficeVoucherSync}
 * im Command `lexoffice:sync-vouchers`. Idempotent über die Voucher-UUID
 * (source_reference); Betrag = total − open (deckt Teilzahlung).
 *
 * Zuordnung: LexofficeVoucher.external_id ↔ ExternalReference(external_type=
 * 'invoice').external_id → lokale Retainer-Invoice → Agreement.
 */
class RetainerVoucherReconciler {
    public function __construct(private readonly CustomerAccountStatementService $statements) {}

    /** @return array{booked: int, revoked: int, skipped: int} */
    public function reconcile(Organization $organization): array {
        $result = ['booked' => 0, 'revoked' => 0, 'skipped' => 0];

        $vouchers = LexofficeVoucher::query()
            ->where('organization_id', $organization->id)
            ->where('archived', false)
            ->whereNotNull('customer_id')
            ->get();

        // UUID → lokale Retainer-Invoice (nur TYPE_RETAINER-Belege dieser Org).
        $invoiceByExternalId = $this->retainerInvoiceMap($organization);

        foreach ($vouchers as $voucher) {
            $invoice = $invoiceByExternalId[$voucher->external_id] ?? null;
            if ($invoice === null) {
                $result['skipped']++;

                continue;
            }

            $agreement = CustomerBillingAgreement::query()
                ->where('customer_id', $invoice->customer_id)
                ->first();
            if ($agreement === null || ! $agreement->isRetainerMode()) {
                $result['skipped']++;

                continue;
            }

            $status = (string) $voucher->voucher_status;
            if ($status === 'voided') {
                $this->statements->revokeLexofficePayment($agreement, $voucher->external_id);
                $this->markInvoice($invoice, Invoice::STATUS_CANCELLED);
                $result['revoked']++;

                continue;
            }

            $total = $voucher->total_amount?->toFloat() ?? 0.0;
            $open = $voucher->open_amount?->toFloat() ?? $total;
            $paid = round($total - $open, 2);

            if ($paid <= 0) {
                $result['skipped']++;

                continue;
            }

            $paidOn = $voucher->voucher_date !== null
                ? Carbon::parse($voucher->voucher_date, Tz::current())
                : Carbon::now(Tz::current());

            $this->statements->bookLexofficePayment(
                $agreement,
                $voucher->external_id,
                $paid,
                $paidOn,
                (string) __('customer-billing.lexoffice_payment_note', ['number' => (string) $voucher->voucher_number]),
            );
            $this->markInvoice($invoice, $open <= 0.005 ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID, $paidOn);
            $result['booked']++;
        }

        return $result;
    }

    /**
     * @return array<string, Invoice> Lexoffice-UUID → lokale Retainer-Invoice.
     */
    private function retainerInvoiceMap(Organization $organization): array {
        $refs = ExternalReference::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficeInvoiceService::EXT_TYPE_INVOICE)
            ->where('referenceable_type', (new Invoice)->getMorphClass())
            ->get(['external_id', 'referenceable_id']);

        if ($refs->isEmpty()) {
            return [];
        }

        $invoices = Invoice::query()
            ->whereIn('id', $refs->pluck('referenceable_id'))
            ->where('type', Invoice::TYPE_RETAINER)
            ->get()
            ->keyBy('id');

        $map = [];
        foreach ($refs as $ref) {
            $invoice = $invoices->get($ref->referenceable_id);
            if ($invoice !== null) {
                $map[$ref->external_id] = $invoice;
            }
        }

        return $map;
    }

    /** Pflegt ausschließlich Whitelist-Felder (status, paid_on) der Invoice. */
    private function markInvoice(Invoice $invoice, string $status, ?Carbon $paidOn = null): void {
        if ($invoice->status === $status) {
            return;
        }
        $invoice->status = $status;
        if ($status === Invoice::STATUS_PAID && $paidOn !== null) {
            $invoice->paid_on = $paidOn;
        }
        $invoice->saveQuietly();
    }
}
