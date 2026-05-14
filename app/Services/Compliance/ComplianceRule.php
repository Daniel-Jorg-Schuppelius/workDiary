<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\ScheduledShift;

interface ComplianceRule
{
    /** Eindeutiger Schalter-Key in `compliance.rules`. */
    public function key(): string;

    /**
     * Prüfe die übergebene Schicht; gib 0..n Verletzungen zurück.
     *
     * @param  array{mode:string, max_hours_day:int, min_rest_hours:int, max_hours_week:int, max_consecutive_days:int, rules:array<string,bool>}  $settings
     * @return list<ComplianceViolation>
     */
    public function check(ScheduledShift $shift, array $settings): array;
}
