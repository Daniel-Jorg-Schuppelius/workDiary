<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceComplianceFinding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

/**
 * Ein einzelner ArbZG-Verstoss auf der Ist-Arbeitszeit (vgl.
 * {@see AttendanceComplianceChecker}). Reines, unveränderliches Ergebnis-
 * objekt — value/threshold sind in Minuten; Ausnahmen je Regel-Art
 * (Tage/Anzahl) liefert {@see unitFor()}.
 */
final class AttendanceComplianceFinding {
    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

    public const UNIT_MINUTES = 'minutes';

    public const UNIT_DAYS = 'days';

    public const UNIT_COUNT = 'count';

    /**
     * Einheit von value/threshold je Regel-Art — Standard sind Minuten;
     * Verzugs- (Tage) und Zähl-Regeln (§11) weichen ab.
     */
    public static function unitFor(string $kind): string {
        return match ($kind) {
            AttendanceComplianceChecker::KIND_LATE_RECORDING => self::UNIT_DAYS,
            AttendanceComplianceChecker::KIND_SUBSTITUTE_REST_DAY,
            AttendanceComplianceChecker::KIND_FREE_SUNDAYS => self::UNIT_COUNT,
            default => self::UNIT_MINUTES,
        };
    }

    /** Anzeige-Formatierung von value/threshold gemäß Einheit (Report/History/CSV). */
    public static function formatValue(string $kind, int $value): string {
        return match (self::unitFor($kind)) {
            self::UNIT_DAYS => trans_choice('compliance.report.unit.days', $value, ['count' => $value]),
            self::UNIT_COUNT => (string) $value,
            default => \App\Support\Formats::duration($value, 'clock'),
        };
    }

    public function __construct(
        public readonly int $userId,
        /** Kalendertag (Y-m-d); bei Wochen-Hinweisen das Wochenende. */
        public readonly string $date,
        /** AttendanceComplianceChecker::KIND_*. */
        public readonly string $kind,
        public readonly string $severity,
        /** Gemessener Wert in Minuten. */
        public readonly int $value,
        /** Schwellwert in Minuten. */
        public readonly int $threshold,
    ) {}

    /**
     * @return array{user_id:int, date:string, kind:string, severity:string, value:int, threshold:int}
     */
    public function toArray(): array {
        return [
            'user_id' => $this->userId,
            'date' => $this->date,
            'kind' => $this->kind,
            'severity' => $this->severity,
            'value' => $this->value,
            'threshold' => $this->threshold,
        ];
    }
}
