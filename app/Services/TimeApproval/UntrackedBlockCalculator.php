<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UntrackedBlockCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeApproval;

use App\Models\{Attendance, TimeEntry};
use Carbon\CarbonImmutable;

/**
 * Leitet die offenen (noch nicht auf ein Projekt/einen Auftrag gebuchten)
 * Zeitblöcke eines Tages ab (MVP-015, Rang 37): Anwesenheitsspannen minus die
 * bereits mit Zeitspanne belegten Zeiteinträge. Ergebnis sind ziehbare
 * Vorschläge für die Quick-Buchung.
 *
 * Nur zeitlich verortete Einträge (started_at + ended_at) verkleinern einen
 * Block; reine Dauer-Einträge ohne Spanne haben keine Position und bleiben
 * daher unberücksichtigt (die Aggregat-Kennzahl „unverteilt" deckt diese ab).
 */
final class UntrackedBlockCalculator {
    public const MIN_BLOCK_MINUTES = 5;

    /**
     * @param  iterable<Attendance>  $attendances
     * @param  iterable<TimeEntry>  $entries
     * @return list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable, minutes: int}>
     */
    public function blocks(iterable $attendances, iterable $entries, CarbonImmutable $now): array {
        $busy = [];
        foreach ($entries as $entry) {
            if ($entry->started_at !== null && $entry->ended_at !== null) {
                $busy[] = [
                    CarbonImmutable::instance($entry->started_at),
                    CarbonImmutable::instance($entry->ended_at),
                ];
            }
        }

        $blocks = [];
        foreach ($attendances as $attendance) {
            if ($attendance->started_at === null) {
                continue;
            }
            $start = CarbonImmutable::instance($attendance->started_at);
            // Offene Anwesenheit: bis „jetzt" rechnen.
            $end = $attendance->ended_at !== null
                ? CarbonImmutable::instance($attendance->ended_at)
                : $now;
            if ($end->lessThanOrEqualTo($start)) {
                continue;
            }

            foreach ($this->subtract($start, $end, $busy) as [$gapStart, $gapEnd]) {
                $minutes = (int) $gapStart->diffInMinutes($gapEnd);
                if ($minutes >= self::MIN_BLOCK_MINUTES) {
                    $blocks[] = ['started_at' => $gapStart, 'ended_at' => $gapEnd, 'minutes' => $minutes];
                }
            }
        }

        usort($blocks, static fn(array $a, array $b): int => $a['started_at']->getTimestamp() <=> $b['started_at']->getTimestamp());

        return $blocks;
    }

    /**
     * Zieht die belegten Intervalle aus [start, end] ab und liefert die
     * verbleibenden freien Lücken.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $busy
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function subtract(CarbonImmutable $start, CarbonImmutable $end, array $busy): array {
        $free = [[$start, $end]];

        foreach ($busy as [$busyStart, $busyEnd]) {
            $next = [];
            foreach ($free as [$freeStart, $freeEnd]) {
                // Kein Überlappen → Lücke bleibt unverändert.
                if ($busyEnd->lessThanOrEqualTo($freeStart) || $busyStart->greaterThanOrEqualTo($freeEnd)) {
                    $next[] = [$freeStart, $freeEnd];

                    continue;
                }
                if ($busyStart->greaterThan($freeStart)) {
                    $next[] = [$freeStart, $busyStart];
                }
                if ($busyEnd->lessThan($freeEnd)) {
                    $next[] = [$busyEnd, $freeEnd];
                }
            }
            $free = $next;
        }

        return $free;
    }
}
