<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LineAwareVoucherPuller.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

use App\Models\Finance\AccountingVoucher;

/**
 * Puller, dessen Programm die Positionen nur im Einzelabruf liefert
 * (sevDesk `GET /Invoice/{id}/getPositions`). Der Belegabruf holt sie nach
 * dem Lauf für Belege ohne `lines_synced_at` nach; Puller mit Positionen in
 * der Liste (easybill, Invoice Ninja, Akaunting, InvoicePlane) speichern sie
 * direkt im `pull()`.
 */
interface LineAwareVoucherPuller extends VoucherPuller {
    /** @return list<MirroredVoucherLine> */
    public function pullLines(int $organizationId, AccountingVoucher $voucher): array;
}
