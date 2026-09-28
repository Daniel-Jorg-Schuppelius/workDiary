<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesPurchaseOrderLines.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Concerns;

use App\Models\Procurement\PurchaseOrderLine;
use Illuminate\Support\Collection;

/**
 * Ordnet eine Position eines Lieferantenbelegs (Lieferschein, Auftragsbestätigung)
 * einer Bestellzeile zu: zuerst über die 1-basierte Zeilennummer der Bestellung,
 * ersatzweise über die Lieferanten-SKU.
 */
trait ResolvesPurchaseOrderLines {
    /** @param Collection<int, PurchaseOrderLine> $orderedLines nach id sortiert */
    private function resolveOrderLine(Collection $orderedLines, ?string $lineId, ?string $sellersItemId): ?PurchaseOrderLine {
        $lineId = trim((string) $lineId);
        if ($lineId !== '' && ctype_digit($lineId)) {
            $byPosition = $orderedLines->get((int) $lineId - 1);
            if ($byPosition instanceof PurchaseOrderLine) {
                return $byPosition;
            }
        }

        $sku = trim((string) $sellersItemId);
        if ($sku !== '') {
            $bySku = $orderedLines->first(static fn (PurchaseOrderLine $line): bool => trim((string) $line->supplier_sku) === $sku);
            if ($bySku instanceof PurchaseOrderLine) {
                return $bySku;
            }
        }

        return null;
    }
}
