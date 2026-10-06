<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisActionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Crisis;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand einer Krisenmaßnahme (MVP-216). Ohne Übergangstabelle: die Akte
 * setzt jeden Stand aus jedem anderen, niemand prüft den Wechsel.
 */
enum CrisisActionStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /**
     * Noch zu erledigen (Überfälligkeit, BCM-Bericht).
     *
     * @return list<self>
     */
    public static function pending(): array {
        return [self::Open, self::InProgress];
    }

    public function isSettled(): bool {
        return ! in_array($this, self::pending(), true);
    }

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
