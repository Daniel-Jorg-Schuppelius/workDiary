<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisContinuityImpactStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Crisis;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Wiederanlaufstand eines kritischen Prozesses (BCM, MVP-219). Ohne
 * Übergangstabelle: die Akte setzt jeden Stand aus jedem anderen.
 */
enum CrisisContinuityImpactStatus: string implements HasLabel {
    use HasOptions;

    case Down = 'down';
    case Degraded = 'degraded';
    case Workaround = 'workaround';
    case Restored = 'restored';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Restored => 'success',
            self::Down => 'error',
            self::Degraded, self::Workaround => 'warning',
        };
    }
}
