<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubResultFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Ergebnisformat des Sportartenprofils: Tore, Punkte je Spielabschnitt, Sätze oder kein Spielergebnis. */
enum ClubResultFormat: string implements HasLabel {
    use HasOptions;

    case Goals = 'goals';
    case PeriodPoints = 'period_points';
    case Sets = 'sets';
    case None = 'none';

    public function label(): string {
        return (string) __('enums.club.result-format.' . $this->value);
    }

    public function hasPeriods(): bool {
        return $this === self::PeriodPoints || $this === self::Sets;
    }
}
