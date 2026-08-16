<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogSourceFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Format einer Lieferanten-Katalogquelle (Feature 050, MVP-091/092). CSV/XLSX
 * laufen über das konfigurierbare Spalten-Mapping, DATANORM (4/5, Feature 107)
 * und BMEcat sind strukturierte Formate mit festem Satzaufbau. ShopInfo ist
 * kein wählbares Quellformat, sondern befüllt als Discovery-Helfer die
 * CSV-Einstellungen (daher bewusst nicht in der Format-Whitelist des
 * SaveSupplierCatalogSourceRequest).
 */
enum CatalogSourceFormat: string implements HasLabel {
    use HasOptions;

    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case ShopInfo = 'shopinfo';
    case Datanorm = 'datanorm';
    case BMEcat = 'bmecat';

    public function label(): string {
        return __('procurement.catalog.format.' . $this->value);
    }
}
