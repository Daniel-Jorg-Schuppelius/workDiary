<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BookingSuggestionBasis.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Merkmal, über das ein Bankumsatz ohne Beleg seinen Kontierungsvorschlag
 * findet (Feature 207, MVP-1660). Die Reihenfolge der Fälle ist die Rangfolge:
 * Mandatsreferenz und Gläubiger-ID vor IBAN vor Stichwort — das spezifischere
 * Merkmal gewinnt, auch wenn ein schwächeres mehr Treffer hätte.
 */
enum BookingSuggestionBasis: string implements HasLabel {
    use HasOptions;

    /** Gleiche Mandatsreferenz (und, wo beide sie tragen, gleiche Gläubiger-ID). */
    case Mandate = 'mandate';

    /** Gleiche Gläubiger-ID der SEPA-Lastschrift. */
    case Creditor = 'creditor';

    /** Gleiche IBAN der Gegenpartei (über den Abdruck verglichen). */
    case Iban = 'iban';

    /** Gleiche Stichwörter im Verwendungszweck (über den Abdruck verglichen). */
    case Keywords = 'keywords';

    public function label(): string {
        return (string) __('enums.finance.booking-suggestion-basis.' . $this->value);
    }

    /** Gewicht für die Stärke des Vorschlags. */
    public function weight(): int {
        return match ($this) {
            self::Mandate, self::Creditor => 3,
            self::Iban => 2,
            self::Keywords => 1,
        };
    }
}
