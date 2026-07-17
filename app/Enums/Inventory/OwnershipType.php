<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OwnershipType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Eigentums-/Verwendungsbindung von Bestand (Feature 048, MVP-067). Bestände
 * unterschiedlicher Eigentumsarten dürfen nicht still zusammengefasst oder
 * gegeneinander verbraucht werden.
 */
enum OwnershipType: string implements HasLabel {
    use HasOptions;

    case Own = 'own';
    case Customer = 'customer';
    case Consignment = 'consignment';
    case Supplier = 'supplier';
    case Project = 'project';

    public function label(): string {
        return match ($this) {
            self::Own => __('inventory.ownership.own'),
            self::Customer => __('inventory.ownership.customer'),
            self::Consignment => __('inventory.ownership.consignment'),
            self::Supplier => __('inventory.ownership.supplier'),
            self::Project => __('inventory.ownership.project'),
        };
    }
}
