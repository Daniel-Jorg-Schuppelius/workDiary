<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemEligibilityChecker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Expense;

use App\Enums\Expense\PerDiemTripStatus;
use App\Models\PerDiemTrip;
use Carbon\CarbonImmutable;

/**
 * 3-Monats-Regel (DE):
 *
 *  - Verpflegungsmehraufwand ist je auswärtiger Tätigkeitsstätte auf 3 Monate begrenzt.
 *  - Eine Unterbrechung von ≥ 4 Wochen setzt den Zähler zurück (unabhängig vom Grund).
 *  - Wir liefern eine Warnung (nicht blockierend), wenn der Trip vermutlich den Zeitraum überschreitet.
 */
class PerDiemEligibilityChecker {
    /**
     * Prüft, ob die 3-Monats-Frist für `workplace_key` des Trips bereits ausgeschöpft ist.
     *
     * @return array{eligible: bool, used_days: int, limit_days: int, reason: ?string}
     */
    public function check(PerDiemTrip $trip): array {
        $limitDays = 90;
        $key = (string) ($trip->workplace_key ?? strtolower(trim((string) $trip->location)));
        if ($key === '') {
            return ['eligible' => true, 'used_days' => 0, 'limit_days' => $limitDays, 'reason' => null];
        }

        $tripStart = CarbonImmutable::parse($trip->started_at)->startOfDay();
        $windowStart = $tripStart->subMonths(6);

        $trips = PerDiemTrip::query()
            ->where('user_id', $trip->user_id)
            ->where('workplace_key', $key)
            ->where('id', '!=', $trip->id ?? 0)
            ->where('status', '!=', PerDiemTripStatus::Cancelled->value)
            ->where('ended_at', '>=', $windowStart)
            ->where('started_at', '<=', $tripStart)
            ->with('days')
            ->orderBy('started_at')
            ->get();

        $usedDays = 0;
        $lastEnd = null;
        foreach ($trips as $prev) {
            $start = CarbonImmutable::parse($prev->started_at)->startOfDay();
            $end = CarbonImmutable::parse($prev->ended_at)->startOfDay();
            // Carbon 3: diffInDays ist signiert — Basis muss der FRÜHERE
            // Zeitpunkt sein, sonst ist die Differenz negativ und der
            // 4-Wochen-Reset feuert nie.
            if ($lastEnd !== null && $lastEnd->diffInDays($start) >= 28) {
                $usedDays = 0;
            }
            $usedDays += (int) $prev->days->count();
            $lastEnd = $end;
        }

        // s. o.: signierte Carbon-3-Differenz — vom früheren zum späteren Datum.
        if ($lastEnd !== null && $lastEnd->diffInDays($tripStart) >= 28) {
            $usedDays = 0;
        }

        $eligible = $usedDays < $limitDays;
        $reason = $eligible
            ? null
            : sprintf('Die 3-Monats-Frist für „%s" ist mit %d Tagen erreicht.', $trip->location, $usedDays);

        return [
            'eligible' => $eligible,
            'used_days' => $usedDays,
            'limit_days' => $limitDays,
            'reason' => $reason,
        ];
    }
}
