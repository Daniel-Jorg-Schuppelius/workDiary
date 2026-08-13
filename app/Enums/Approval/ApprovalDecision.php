<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalDecision.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Approval;

/**
 * MVP-531: Entscheidung einer Genehmigungsstufe.
 */
enum ApprovalDecision: string {
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string {
        return match ($this) {
            self::Approved => __('Freigegeben'),
            self::Rejected => __('Abgelehnt'),
        };
    }
}
