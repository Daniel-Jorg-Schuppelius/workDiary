<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierPurchaseOriginals.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Purchase;

use App\Models\Platform\Organization;
use App\Models\Supplier\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Zusatzfähigkeit einer {@see PurchaseDocumentSource}: Belege eines Lieferanten
 * samt Original. Darüber liest das Reselling-Register Anbieterrechnungen, die
 * nur im Buchhaltungsprogramm liegen.
 */
interface SupplierPurchaseOriginals {
    /**
     * Zuteilbare Belege des Lieferanten ab `$from`, älteste zuerst.
     *
     * @return Collection<int, PurchaseDocument>
     */
    public function forSupplier(Organization $organization, Supplier $supplier, CarbonImmutable $from): Collection;

    /**
     * Original des Belegs als lokale Datei; null ohne Belegbild.
     *
     * @return array{contents: string, mime: ?string, path: string, name: string}|null
     */
    public function original(Organization $organization, PurchaseDocument $document): ?array;
}
