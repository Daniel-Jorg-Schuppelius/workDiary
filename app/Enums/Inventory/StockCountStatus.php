<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockCountStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Status einer stichtagsbezogenen Inventur (Feature 048, MVP-069). Zählen,
 * Prüfen und Freigeben können verschiedene Personen übernehmen: „Zählung
 * abschließen“ friert die Zählmengen zur Prüfung ein (MVP-1620).
 */
enum StockCountStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Counting = 'counting';   // Sollbestand eingefroren, Zählung läuft
    case Review = 'review';       // Differenzen werden geprüft
    case Completed = 'completed'; // Differenzen freigegeben & gebucht
    case Cancelled = 'cancelled';

    public function label(): string {
        return __('inventory.count_status.' . $this->value);
    }

    public function isOpen(): bool {
        return $this === self::Counting || $this === self::Review;
    }

    /** Zählmengen sind nur während der Zählung erfassbar. */
    public function isCounting(): bool {
        return $this === self::Counting;
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Counting => [self::Review, self::Completed, self::Cancelled],
            // Die Prüfung kann die Zählung zur Korrektur wieder öffnen.
            self::Review => [self::Counting, self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }
}
