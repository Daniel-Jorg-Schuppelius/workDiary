<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentStatusProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Contracts;

use App\Models\Invoicing\Invoice;

/**
 * Zahlstatus einer Rechnung aus der Bankzuordnung (MVP-863). Definiert von
 * Invoicing, gebunden von Finance ({@see \App\Services\Finance\ReconciliationService});
 * ohne Finanzmodul gilt die Null-Bindung: keine Bankzuordnung, offener Betrag
 * = Rechnungsbetrag abzüglich Kassenzahlungen.
 */
interface PaymentStatusProvider {
    /** Summe der bestätigten Bankzuordnungen auf die Rechnung. */
    public function allocatedSum(Invoice $invoice): float;
}
