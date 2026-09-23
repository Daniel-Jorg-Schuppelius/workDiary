<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMembershipKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Mitgliedschaftsart (Feature 159, MVP-842): je Zeitraum im Verlauf
 * ({@see \App\Models\Club\ClubMembershipPeriod}); der aktuelle Stand steht
 * zusätzlich am Mitglied. Eine Pause bleibt eine Mitgliedschaft.
 */
enum ClubMembershipKind: string implements HasLabel {
    use HasOptions;

    case Active = 'active';
    case Passive = 'passive';
    case Supporting = 'supporting';
    case Paused = 'paused';
    // Gastspieler eines Partnervereins (MVP-852): ohne Beitrag, Login oder Gruppenmitgliedschaft.
    case Guest = 'guest';

    public function label(): string {
        return (string) __('enums.club.membership-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::Passive => 'info',
            self::Supporting => 'secondary',
            self::Paused => 'warning',
            self::Guest => 'ghost',
        };
    }
}
