<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashBookPosting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Passenger\Contracts;

use App\Models\Finance\{CashEntry, CashRegister};

/**
 * Barumsatz einer Fahrdienst-Abrechnung ins Kassenbuch (Welle 4.5):
 * definiert vom Fahrdienst, gebunden vom Finanzmodul (`CashBookService`).
 * Null-Bindung: Kassenbuch nicht verfügbar.
 */
interface CashBookPosting {
    /**
     * @param  array{booked_on: string, direction: string, amount: float|string, purpose: string,
     *               tax_rate?: float|string|null, counterparty?: string|null, invoice_id?: int|null,
     *               created_by?: int|null}  $data
     */
    public function record(CashRegister $register, array $data): CashEntry;
}
