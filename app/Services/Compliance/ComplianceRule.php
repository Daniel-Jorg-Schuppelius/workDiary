<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
