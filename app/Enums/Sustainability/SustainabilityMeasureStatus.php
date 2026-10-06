<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityMeasureStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sustainability;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand einer Verbesserungsmaßnahme (Feature 071, MVP-229). Ohne
 * Übergangstabelle: die Statuspflege schreibt aus jedem Stand.
 */
enum SustainabilityMeasureStatus: string implements HasLabel {
    use HasOptions;

    case Proposed = 'proposed';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Discarded = 'discarded';

    /**
     * Noch umzusetzen (Kennzahl im Dashboard).
     *
     * @return list<self>
     */
    public static function open(): array {
        return [self::Proposed, self::Approved, self::InProgress];
    }

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
