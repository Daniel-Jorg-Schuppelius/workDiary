<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScanAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

/**
 * Mobile Buchungsaktion per Scan (Feature 048, E5).
 */
enum ScanAction: string {
    case Receipt = 'receipt';   // Wareneingang
    case Issue = 'issue';       // Entnahme
    case Transfer = 'transfer'; // Umlagerung

    public function label(): string {
        return __('inventory.scan.action.' . $this->value);
    }
}
