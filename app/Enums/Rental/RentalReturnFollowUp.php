<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalReturnFollowUp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Folgeentscheidung der Rücknahmeprüfung (MVP-265): Reinigung/Reparatur
 * erzeugen Belegungsfenster bzw. Sperren im gemeinsamen Modell (D12),
 * claim übergibt kontrolliert an die Reklamation (MVP-267).
 */
enum RentalReturnFollowUp: string implements HasLabel {
    use HasOptions;

    case None = 'none';
    case Cleaning = 'cleaning';
    case Repair = 'repair';
    case Block = 'block';
    case Claim = 'claim';

    public function label(): string {
        return match ($this) {
            self::None => (string) __('Keine Folge'),
            self::Cleaning => (string) __('Reinigung erforderlich'),
            self::Repair => (string) __('Reparatur erforderlich'),
            self::Block => (string) __('Sperren'),
            self::Claim => (string) __('Reklamation eröffnen'),
        };
    }

    public function blocksAsset(): bool {
        return in_array($this, [self::Repair, self::Block], true);
    }
}
