<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotAllocation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\StockLot;

/**
 * Zuteilung einer Abgangsmenge auf Chargen (Feature 048, E2), gebaut von
 * {@see LotStockReader::allocate()}: je Teil eine Charge oder `null` für
 * Bestand ohne Charge; die Teile summieren sich zur Abgangsmenge.
 */
final class LotAllocation {
    /** @param non-empty-list<array{lot: StockLot|null, qty: numeric-string}> $parts */
    public function __construct(public readonly array $parts) {}
}
