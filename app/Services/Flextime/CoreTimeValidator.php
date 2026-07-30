<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoreTimeValidator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Flextime;

use App\Models\{TimeEntry, User, WorkSchedule};
use App\Support\Tz;

/**
 * Kernzeit-/Rahmenzeit-/Pflichtpausen-Prüfung je Zeiteintrag (Vollreview W2.1):
 * genutzt als nicht blockierende Warnung im Speicherpfad
 * ({@see \App\Http\Controllers\TimeEntryController}) und als Finding-Quelle im
 * Compliance-Scan ({@see \App\Services\Compliance\CoreTimeScanService}).
 */
class CoreTimeValidator {
    public const KIND_FRAME_TIME = 'frameTime';

    public const KIND_CORE_TIME = 'coreTime';

    public const KIND_ENTRY_BREAK = 'entryBreakMissing';

    public function __construct(protected WorkScheduleResolver $resolver) {}

    /**
     * Liefert eine Liste mit Verstoß-Beschreibungen (i18n-Key/-String). Leeres Array = ok.
     *
     * @return array<int, string>
     */
    public function violations(User $user, TimeEntry $entry): array {
        return array_map(
            static fn (array $violation): string => $violation['message'],
            $this->structuredViolations($user, $entry),
        );
    }

    /**
     * Strukturierte Verstöße für den Compliance-Scan: kind + Mess-/Schwellwert
     * in Minuten. Bedingungen und Meldungstexte sind identisch zu violations().
     *
     * @return list<array{kind: string, message: string, value: int, threshold: int}>
     */
    public function structuredViolations(User $user, TimeEntry $entry): array {
        if (! $entry->started_at || ! $entry->ended_at) {
            return [];
        }

        $schedule = $this->resolver->for($user, $entry->started_at);
        $issues = [];

        // Kern-/Rahmenzeit-Regeln gelten nur für Gleitzeit; andere Typen
        // (Wochenarbeitszeit, wochentagsweise, Vertrauensarbeitszeit) kennen
        // keine Kernzeit. Die Pflichtpause unten gilt dagegen für alle.
        if (! $schedule->schedule_type->usesCoreTime()) {
            return $this->breakOnly($schedule, $entry);
        }

        // Vergleiche auf Minutenbasis in der Anzeige-Zeitzone: Einträge sind
        // UTC gespeichert, Schedule-Zeiten sind Wanduhrzeiten ('HH:MM' oder
        // 'HH:MM:SS') — roher Stringvergleich wäre doppelt falsch.
        $tz = Tz::current();
        $localStart = $entry->started_at->copy()->setTimezone($tz);
        $localEnd = $entry->ended_at->copy()->setTimezone($tz);
        $startMin = self::minutesOfDay($localStart->format('H:i'));
        $endMin = self::minutesOfDay($localEnd->format('H:i'));

        if ($schedule->frame_start && $startMin < self::minutesOfDay($schedule->frame_start)) {
            $issues[] = [
                'kind' => self::KIND_FRAME_TIME,
                'message' => __('Beginn vor Rahmenzeit (:t).', ['t' => substr($schedule->frame_start, 0, 5)]),
                'value' => max(0, self::minutesOfDay($schedule->frame_start) - $startMin),
                'threshold' => 0,
            ];
        }
        if ($schedule->frame_end && $endMin > self::minutesOfDay($schedule->frame_end)) {
            $issues[] = [
                'kind' => self::KIND_FRAME_TIME,
                'message' => __('Ende nach Rahmenzeit (:t).', ['t' => substr($schedule->frame_end, 0, 5)]),
                'value' => max(0, $endMin - self::minutesOfDay($schedule->frame_end)),
                'threshold' => 0,
            ];
        }

        // Kernzeit: an Arbeitstagen muss in [core_start, core_end] gearbeitet werden
        if ($schedule->core_start && $schedule->core_end && $schedule->appliesOnWeekday($localStart->dayOfWeekIso)) {
            $coreStart = self::minutesOfDay($schedule->core_start);
            $coreEnd = self::minutesOfDay($schedule->core_end);
            $coversCore = $startMin <= $coreStart && $endMin >= $coreEnd;
            if (! $coversCore) {
                $issues[] = [
                    'kind' => self::KIND_CORE_TIME,
                    'message' => __('Kernarbeitszeit (:a–:b) nicht abgedeckt.', [
                        'a' => substr($schedule->core_start, 0, 5),
                        'b' => substr($schedule->core_end, 0, 5),
                    ]),
                    // Messwert: tatsächlich abgedeckte Kernzeit-Minuten.
                    'value' => max(0, min($endMin, $coreEnd) - max($startMin, $coreStart)),
                    'threshold' => max(0, $coreEnd - $coreStart),
                ];
            }
        }

        return array_merge($issues, $this->breakOnly($schedule, $entry));
    }

    /**
     * Nur die Pflichtpausen-Prüfung – gilt unabhängig vom Arbeitszeit-Typ.
     *
     * @return list<array{kind: string, message: string, value: int, threshold: int}>
     */
    private function breakOnly(WorkSchedule $schedule, TimeEntry $entry): array {
        if ((int) $entry->minutes >= $schedule->break_after_minutes && (int) $entry->break_minutes < $schedule->break_minutes) {
            return [[
                'kind' => self::KIND_ENTRY_BREAK,
                'message' => __('Pflichtpause :m min nicht erreicht.', ['m' => $schedule->break_minutes]),
                'value' => (int) $entry->break_minutes,
                'threshold' => (int) $schedule->break_minutes,
            ]];
        }

        return [];
    }

    /** 'HH:MM[:SS]' → Minuten seit Mitternacht. */
    private static function minutesOfDay(string $time): int {
        [$h, $m] = array_map('intval', array_pad(explode(':', $time), 2, '0'));

        return $h * 60 + $m;
    }
}
