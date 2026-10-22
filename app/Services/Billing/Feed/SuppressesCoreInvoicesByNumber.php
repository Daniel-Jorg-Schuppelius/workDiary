<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SuppressesCoreInvoicesByNumber.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dublettenregel 1 (extern führt) für Spiegel, deren Belegnummer in einer
 * Spalte steht: eine lokale Rechnung, deren Nummer oder Programmnummer als
 * gespiegelter Beleg existiert, erscheint nur als Beleg (Lexoffice,
 * orgaMAX; Konsolidierungs-Audit 2026-10-21, ko-20).
 */
trait SuppressesCoreInvoicesByNumber {
    private function suppressByMirroredNumber(Builder $invoices, string $table, string $numberColumn): void {
        $invoices->whereNotExists(static function (Builder $sub) use ($table, $numberColumn): void {
            $sub->select(DB::raw(1))
                ->from($table)
                ->whereColumn($table . '.organization_id', 'invoices.organization_id')
                ->where(static function (Builder $q) use ($table, $numberColumn): void {
                    $q->whereColumn($table . '.' . $numberColumn, 'invoices.number')
                        ->orWhereColumn($table . '.' . $numberColumn, 'invoices.external_number');
                });
        });
    }
}
