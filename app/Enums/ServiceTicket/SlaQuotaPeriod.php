<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaQuotaPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServiceTicket;

use Carbon\CarbonInterface;

/**
 * Abrechnungsperiode eines SLA-Kontingents (Feature 010 → Rang 44). Bestimmt das
 * Zeitfenster, über das der Inklusivzeit-Verbrauch gerechnet wird, und liefert
 * einen stabilen Perioden-Schlüssel für die einmalige Warnung je Periode.
 */
enum SlaQuotaPeriod: string {
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /**
     * Zeitfenster [Start, Ende] der Periode, in der die Referenz liegt.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function window(CarbonInterface $reference): array {
        return match ($this) {
            self::Month => [$reference->copy()->startOfMonth(), $reference->copy()->endOfMonth()],
            self::Quarter => [$reference->copy()->startOfQuarter(), $reference->copy()->endOfQuarter()],
            self::Year => [$reference->copy()->startOfYear(), $reference->copy()->endOfYear()],
        };
    }

    /** Stabiler Perioden-Schlüssel (z. B. `2026-07`, `2026-Q3`, `2026`). */
    public function key(CarbonInterface $reference): string {
        return match ($this) {
            self::Month => $reference->format('Y-m'),
            self::Quarter => $reference->format('Y') . '-Q' . $reference->quarter,
            self::Year => $reference->format('Y'),
        };
    }
}
