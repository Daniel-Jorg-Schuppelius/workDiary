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
 * objekt — value/threshold sind in Minuten.
 */
final class AttendanceComplianceFinding {
    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

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
