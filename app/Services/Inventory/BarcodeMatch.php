<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BarcodeMatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\BarcodeMatchType;
use App\Models\{Article, ArticleVariant, StockLot, StockSerial};

/**
 * Ergebnis einer Barcode-Auflösung (Feature 048, E5). Trägt die Trefferart und
 * die aufgelösten Entitäten; `variant` ist – sofern bestimmbar – die
 * bestandsführende Variante für eine anschließende Buchung.
 */
final class BarcodeMatch {
    public function __construct(
        public readonly BarcodeMatchType $type,
        public readonly ?ArticleVariant $variant = null,
        public readonly ?StockSerial $serial = null,
        public readonly ?StockLot $lot = null,
        public readonly ?Article $article = null,
    ) {}

    public function found(): bool {
        return $this->type !== BarcodeMatchType::Unknown;
    }
}
