<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BarcodeMatchType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

/**
 * Trefferart der Barcode-Auflösung (Feature 048, E5). Die Reihenfolge bei der
 * Auflösung geht vom Spezifischsten (Seriennummer) zum Allgemeinsten (Artikel).
 */
enum BarcodeMatchType: string {
    case Serial = 'serial';
    case Lot = 'lot';
    case Variant = 'variant';
    case Article = 'article';
    case Unknown = 'unknown';
}
