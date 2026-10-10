<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettleCommissionOnExternalInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Events\Invoicing\{ExternalInvoiceCancelled, ExternalInvoicePaymentReceived, ExternalInvoicePaymentReverted};
use App\Listeners\ModuleListener;
use App\Services\Sales\Commission\CommissionDocuments;
use App\Services\Sales\CommissionAccrualService;

/** Provision an gespiegelten Rechnungen des Buchhaltungsprogramms (MVP-1119). */
final class SettleCommissionOnExternalInvoice extends ModuleListener {
    public function __construct(
        private readonly CommissionAccrualService $accrual,
        private readonly CommissionDocuments $documents,
    ) {}

    protected function module(): string {
        return 'sales';
    }

    public function handle(ExternalInvoicePaymentReceived|ExternalInvoicePaymentReverted|ExternalInvoiceCancelled $event): void {
        if (! $this->shouldHandle($event->organizationId)) {
            return;
        }
        $document = $this->documents->find($event->documentType, $event->organizationId, $event->documentId);
        if ($document === null) {
            return;
        }

        match (true) {
            $event instanceof ExternalInvoicePaymentReceived => $this->accrual->onInvoicePaid($document),
            $event instanceof ExternalInvoicePaymentReverted => $this->accrual->onPaymentReverted($document),
            $event instanceof ExternalInvoiceCancelled => $this->accrual->onInvoiceCancelled($document),
        };
    }
}
