<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccrueCommissionOnPayment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Enums\Invoicing\InvoiceStatus;
use App\Events\Invoicing\InvoicePaymentReceived;
use App\Listeners\ModuleListener;
use App\Services\Sales\CommissionAccrualService;

/** Provision auf Teilzahlungen (MVP-989); „bezahlt“ läuft weiter über den Statuswechsel der Rechnung. */
final class AccrueCommissionOnPayment extends ModuleListener {
    public function __construct(private readonly CommissionAccrualService $accrual) {}

    protected function module(): string {
        return 'sales';
    }

    public function handle(InvoicePaymentReceived $event): void {
        $invoice = $event->invoice;
        if ($invoice->status !== InvoiceStatus::PartiallyPaid || ! $this->shouldHandle((int) $invoice->organization_id)) {
            return;
        }
        $this->accrual->accrue($invoice);
    }
}
