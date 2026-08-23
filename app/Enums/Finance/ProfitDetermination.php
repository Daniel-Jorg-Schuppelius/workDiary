<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProfitDetermination.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fachliches Auswertungsprofil der lokalen Buchhaltung (Feature 125, MVP-671).
 *
 * Der technische Buchungskern arbeitet IMMER mit ausgeglichenen Soll-/Haben-
 * Zeilen; das Profil ändert nur die Auswertungslogik (§ 4 Abs. 3 EStG vs.
 * §§ 238/239 HGB), nicht die Unveränderlichkeits- und Nachweisregeln. Zwei
 * konkurrierende Datenmodelle wären der teurere Weg zum selben Ergebnis.
 */
enum ProfitDetermination: string implements HasLabel {
    use HasOptions;

    /** Einnahmenüberschussrechnung: Zufluss/Abfluss (§ 4 Abs. 3 EStG). */
    case Euer = 'euer';

    /** Laufende doppelte Buchführung (§§ 238/239 HGB, § 141 AO). */
    case DoubleEntry = 'double_entry';

    public function label(): string {
        return (string) __('enums.finance.profit-determination.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Euer => 'accent',
            self::DoubleEntry => 'primary',
        };
    }

    /** Wird der Gewinn nach Zufluss/Abfluss ermittelt (statt periodengerecht)? */
    public function isCashBasis(): bool {
        return $this === self::Euer;
    }
}
