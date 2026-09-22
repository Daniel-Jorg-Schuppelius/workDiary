<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventVisibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Sichtbarkeit eines Vereinstermins (MVP-843): ganzer Verein, bestimmte
 * Gruppen oder persönliche Einladung. Sichtbarkeit ist nicht Zulassung —
 * Anmeldung und Prüfungszulassung bleiben getrennte Angaben.
 */
enum ClubEventVisibility: string implements HasLabel {
    use HasOptions;

    case Club = 'club';
    case Groups = 'groups';
    case Invited = 'invited';

    public function label(): string {
        return (string) __('enums.club.event-visibility.' . $this->value);
    }
}
