<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubLineupSlot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Platz in der Aufstellung: Feld/Bank (Mannschaftssport) oder Einzel/Doppel (Rückschlagsport). */
enum ClubLineupSlot: string implements HasLabel {
    use HasOptions;

    case Field = 'field';
    case Bench = 'bench';
    case Single = 'single';
    case Double = 'double';

    public function label(): string {
        return (string) __('enums.club.lineup-slot.' . $this->value);
    }

    /** Eine Person zählt je Schlüssel einmal: Feld/Bank teilen sich „team“, Einzel und Doppel sind je ein Platz. */
    public function slotKey(): string {
        return match ($this) {
            self::Field, self::Bench => 'team',
            self::Single => 'single',
            self::Double => 'double',
        };
    }

    public function isPairing(): bool {
        return $this === self::Single || $this === self::Double;
    }
}
