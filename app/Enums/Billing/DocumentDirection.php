<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentDirection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Billing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Geldflussrichtung eines Belegs (Feature 105, MVP-542).
 *
 * Bewusst getrennt von der Kontaktart: eine Gutschrift an einen Kunden ist
 * Outgoing (Erlösminderung), nicht Incoming.
 */
enum DocumentDirection: string implements HasLabel {
    use HasOptions;

    case Outgoing = 'outgoing';
    case Incoming = 'incoming';
    case Neutral = 'neutral';

    public function label(): string {
        return (string) __('enums.billing.direction.' . $this->value);
    }

    /** Material-Symbol für die Richtungsmarkierung in Listen. */
    public function icon(): string {
        return match ($this) {
            self::Outgoing => 'north_east',
            self::Incoming => 'south_west',
            self::Neutral => 'remove',
        };
    }

    /** Ohne Geldwirkung → zählt nur als Anzahl, nie in Summen. */
    public function isMonetary(): bool {
        return $this !== self::Neutral;
    }
}
