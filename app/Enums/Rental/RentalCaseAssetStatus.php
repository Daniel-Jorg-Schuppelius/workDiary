<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalCaseAssetStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand eines Leihobjekts in der Verleihakte (MVP-263–265). Ohne
 * Übergangstabelle: Übergabe, Rücknahme und Tausch prüfen den Stand der
 * Akte, nicht den der Position.
 */
enum RentalCaseAssetStatus: string implements HasLabel {
    use HasOptions;

    case Planned = 'planned';
    case HandedOver = 'handed_over';
    case Returned = 'returned';
    case Swapped = 'swapped';

    /**
     * Noch nicht zurück: hält die Akte offen und lässt sich tauschen.
     *
     * @return list<self>
     */
    public static function open(): array {
        return [self::Planned, self::HandedOver];
    }

    public function isOpen(): bool {
        return in_array($this, self::open(), true);
    }

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
