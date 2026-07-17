<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcurementStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Manufacturing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Beschaffungsbedarfs/offenen Punkts (Feature 048,
 * Fehlmaterialprozess). Vollständige Bestellungen sind Folgeausbau.
 */
enum ProcurementStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Ordered = 'ordered';
    case Closed = 'closed';

    public function label(): string {
        return __('manufacturing.procurement_status.' . $this->value);
    }
}
