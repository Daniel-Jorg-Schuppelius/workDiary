<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\TimeEntry;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum TimeEntryKind: string implements HasLabel {
    use HasOptions;

    case Work = 'work';
    case Travel = 'travel';
    case Standby = 'standby';

    /** Laufzeit eines Geräts oder Prozesses (Feature 193): keine Zeit einer Person. */
    case Machine = 'machine';

    public function label(): string {
        return (string) __('enums.time_entry.kind.' . $this->value);
    }

    /**
     * Zeit einer Person — zählt zu Gleitzeit, Pausen, Lohn, Auszahlung und
     * SLA-Kontingent. Gerätelaufzeit ist es nicht.
     */
    public function isPersonTime(): bool {
        return $this !== self::Machine;
    }

    /** Längste Dauer eines Eintrags: 24 h je Person, 31 Tage je Gerätelaufzeit. */
    public function maxMinutes(): int {
        return $this === self::Machine ? 31 * 1440 : 1440;
    }

    /**
     * Bänder für gestapelte Zeit-Charts (x-charts.stacked-bar bzw. das
     * Print-Pendant) — Reihenfolge = Stapelreihenfolge unten → oben.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function chartBands(): array {
        return array_map(
            fn(self $kind): array => ['key' => $kind->value, 'label' => $kind->label()],
            self::cases(),
        );
    }
}
