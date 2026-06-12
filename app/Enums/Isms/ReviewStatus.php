<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReviewStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status einer Managementbewertung (Feature 046, Inkrement C): draft →
 * approved. Die Freigabe setzt approved_by_user_id + approved_at
 * (046-Prinzip „Freigabe mit Person/Zeitpunkt/Gegenstand"); freigegebene
 * Bewertungen sind NICHT mehr editierbar (AuditService erzwingt).
 */
enum ReviewStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Approved = 'approved';

    public function label(): string {
        return (string) __('enums.isms.review-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'warning',
            self::Approved => 'success',
        };
    }
}
