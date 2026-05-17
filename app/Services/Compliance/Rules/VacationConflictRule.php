<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationConflictRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance\Rules;

use App\Models\ScheduledShift;
use App\Models\Vacation;
use App\Services\Compliance\ComplianceRule;
use App\Services\Compliance\ComplianceViolation;

/** Schicht fällt in genehmigten Urlaub des Mitarbeiters. */
final class VacationConflictRule implements ComplianceRule
{
    public function key(): string
    {
        return 'vacation_conflict';
    }

    public function check(ScheduledShift $shift, array $settings): array
    {
        $dateStr = $shift->date->format('Y-m-d');

        $vacation = Vacation::query()
            ->where('user_id', $shift->user_id)
            ->whereIn('status', [Vacation::STATUS_APPROVED, Vacation::STATUS_PENDING])
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->first();

        if (! $vacation) {
            return [];
        }

        return [
            new ComplianceViolation(
                code: 'vacation_conflict',
                severity: $vacation->status === Vacation::STATUS_APPROVED
                    ? ComplianceViolation::SEVERITY_ERROR
                    : ComplianceViolation::SEVERITY_WARNING,
                message: __('Mitarbeiter hat am :date :status Urlaub (:from – :to).', [
                    'date' => $shift->date->format('d.m.Y'),
                    'status' => $vacation->status === Vacation::STATUS_APPROVED ? __('genehmigten') : __('beantragten'),
                    'from' => $vacation->start_date->format('d.m.Y'),
                    'to' => $vacation->end_date->format('d.m.Y'),
                ]),
                relatedShiftIds: [],
                context: ['vacation_id' => $vacation->id, 'status' => $vacation->status],
            ),
        ];
    }
}
