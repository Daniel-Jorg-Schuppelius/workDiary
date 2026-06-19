<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcurementMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Manufacturing;

/**
 * Beschaffungsart eines Erzeugnisses (Feature 047/048, E7): Eigenfertigung,
 * Zukauf oder Fremdfertigung (Lohnauftrag mit Beistellmaterial).
 */
enum ProcurementMode: string {
    case InHouse = 'in_house';
    case Purchase = 'purchase';
    case Subcontract = 'subcontract';

    public function label(): string {
        return __('manufacturing.procurement_mode.' . $this->value);
    }
}
