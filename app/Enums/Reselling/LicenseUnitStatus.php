<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseUnitStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bestandsstatus einer Einzellizenz (MVP-1024). Abgeleitet, nie gespeichert:
 * aktive Zuordnung vor Sperre vor Vollständigkeit — die Kategorien sind
 * disjunkt, gekauft = verfügbar + verkauft + unvollständig + gesperrt.
 */
enum LicenseUnitStatus: string implements HasLabel {
    use HasOptions;

    case Available = 'available';
    case Sold = 'sold';
    case Incomplete = 'incomplete';
    case Blocked = 'blocked';

    public static function derive(bool $sold, bool $blocked, bool $complete): self {
        return match (true) {
            $sold => self::Sold,
            $blocked => self::Blocked,
            $complete => self::Available,
            default => self::Incomplete,
        };
    }

    public function label(): string {
        return (string) __('resale.license.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Available => 'success',
            self::Sold => 'info',
            self::Incomplete => 'warning',
            self::Blocked => 'error',
        };
    }
}
