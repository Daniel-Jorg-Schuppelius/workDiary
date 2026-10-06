<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockLotStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand einer Charge (Feature 047/048, E2): aktiv, gesperrt oder in eine andere Charge zusammengeführt. */
enum StockLotStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Active = 'active';
    case Blocked = 'blocked';
    case Merged = 'merged';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    /**
     * Nur aktive Chargen lassen sich zusammenführen — sonst käme gesperrter
     * Bestand über die Zielcharge ohne Freigabe wieder in den Umlauf.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Active => [self::Blocked, self::Merged],
            self::Blocked => [self::Active],
            self::Merged => [],
        };
    }
}
