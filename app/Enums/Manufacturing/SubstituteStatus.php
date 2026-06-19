<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubstituteStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Manufacturing;

/**
 * Status einer Ersatzmaterial-Abweichung (Feature 048, Fehlmaterialprozess).
 */
enum SubstituteStatus: string {
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string {
        return __('manufacturing.substitute_status.' . $this->value);
    }
}
