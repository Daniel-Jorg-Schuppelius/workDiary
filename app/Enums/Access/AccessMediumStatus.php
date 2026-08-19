<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessMediumStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Access;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Verbleib eines Zutrittsmediums (Feature 092): jedes Medium hat jederzeit
 * genau einen Status. `lost` und `blocked` sind getrennt — verloren heißt
 * noch nicht in der Anlage gesperrt; genau diese Lücke macht die
 * Sperr-Aufgabe sichtbar.
 */
enum AccessMediumStatus: string implements HasLabel {
    use HasOptions;

    case InStock = 'in_stock';
    case Issued = 'issued';
    case Lost = 'lost';
    case Blocked = 'blocked';
    case Retired = 'retired';

    public function label(): string {
        return (string) __('enums.access.medium_status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::InStock => 'neutral',
            self::Issued => 'primary',
            self::Lost => 'error',
            self::Blocked => 'warning',
            self::Retired => 'ghost',
        };
    }
}
