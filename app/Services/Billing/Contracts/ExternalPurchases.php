<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalPurchases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Services\Billing\Dto\{ExternalCategorySpend, ExternalPurchase};
use Carbon\CarbonInterface;

/** Einkaufsbelege aller angebundenen Buchhaltungsprogramme zusammen (MVP-1036). */
interface ExternalPurchases {
    /**
     * @param  list<int>|null  $supplierIds
     * @param  list<int>  $exceptSupplierIds  in Auswertungen ausgeblendete Lieferanten (MVP-1121)
     * @return list<ExternalPurchase>
     */
    public function purchases(?CarbonInterface $from, ?CarbonInterface $to, ?array $supplierIds = null, array $exceptSupplierIds = []): array;

    /**
     * @param  list<int>  $exceptSupplierIds
     * @return array{rows: list<ExternalCategorySpend>, pending: int}
     */
    public function categorySpend(CarbonInterface $from, CarbonInterface $to, array $exceptSupplierIds = []): array;

    /** @return array{open: float, overdue: float, count: int} */
    public function openPayables(int $organizationId, string $today): array;
}
