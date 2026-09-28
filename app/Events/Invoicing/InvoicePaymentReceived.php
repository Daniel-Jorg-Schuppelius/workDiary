<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePaymentReceived.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Invoicing;

use App\Models\Invoicing\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Zahlung auf eine Rechnung zugeordnet (Bankabgleich oder Kasse, MVP-989) —
 * auch eine weitere Teilzahlung, die den Status nicht ändert. Synchron: Die
 * Folgen (Provision auf Teilzahlungen) gehören zur Zahlung.
 */
final class InvoicePaymentReceived {
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
