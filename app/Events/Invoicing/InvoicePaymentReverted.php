<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePaymentReverted.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Invoicing;

use App\Models\Invoicing\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Zahlung im Bankabgleich zurückgenommen (Zuordnung gelöst oder Rückläufer),
 * die Rechnung ist nicht mehr voll gedeckt (MVP-989). Synchron wie
 * {@see InvoicePaymentReceived}.
 */
final class InvoicePaymentReverted {
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
