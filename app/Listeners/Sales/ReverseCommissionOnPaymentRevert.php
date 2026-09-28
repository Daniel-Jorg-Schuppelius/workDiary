<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReverseCommissionOnPaymentRevert.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Events\Invoicing\InvoicePaymentReverted;
use App\Listeners\ModuleListener;
use App\Services\Sales\CommissionAccrualService;

/** Provision auf den nach der Rücknahme bezahlten Anteil zurückrechnen (MVP-989). */
final class ReverseCommissionOnPaymentRevert extends ModuleListener {
    public function __construct(private readonly CommissionAccrualService $accrual) {}

    protected function module(): string {
        return 'sales';
    }

    public function handle(InvoicePaymentReverted $event): void {
        if ($this->shouldHandle((int) $event->invoice->organization_id)) {
            $this->accrual->onPaymentReverted($event->invoice);
        }
    }
}
