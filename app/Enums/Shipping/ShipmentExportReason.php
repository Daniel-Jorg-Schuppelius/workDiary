<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentExportReason.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Shipping;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Versandgrund der Zollpapiere (MVP-1007), angelehnt an die Kategorien der CN23. */
enum ShipmentExportReason: string implements HasLabel {
    use HasOptions;

    case Sale = 'sale';
    case Gift = 'gift';
    case Sample = 'sample';
    case Documents = 'documents';
    case ReturnedGoods = 'returned_goods';
    case Repair = 'repair';
    case Other = 'other';

    public function label(): string {
        return (string) __('shipping.customs.reasons.' . $this->value);
    }
}
