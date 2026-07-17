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
 * Format einer Lieferanten-Katalogquelle (Feature 050, MVP-091/092). Erste
 * Strecke ist CSV; DATANORM/BMEcat/Shopinfo sind vorgesehen, aber noch nicht
 * implementiert.
 */
enum CatalogSourceFormat: string implements HasLabel {
    use HasOptions;

    case Csv = 'csv';
    case ShopInfo = 'shopinfo';
    case Datanorm = 'datanorm';
    case BMEcat = 'bmecat';

    public function label(): string {
        return __('procurement.catalog.format.' . $this->value);
    }
}
