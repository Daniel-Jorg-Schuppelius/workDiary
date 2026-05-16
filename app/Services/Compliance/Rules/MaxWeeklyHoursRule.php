<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaxWeeklyHoursRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance\Rules;

use App\Models\ScheduledShift;
use App\Services\Compliance\ComplianceRule;
use App\Services\Compliance\ComplianceViolation;
use App\Services\Compliance\ResolvesShiftTiming;
use Carbon\CarbonImmutable;

/** Wochenarbeitszeit (default 48h, ISO-Woche). */
final class MaxWeeklyHoursRule implements ComplianceRule {
    use ResolvesShiftTiming;

    public function key(): string {
        return 'max_weekly_hours';
    }

    public function check(ScheduledShift $shift, array $settings): array {
        $maxH = (float) $settings['max_hours_week'];
        $iv = $this->resolveInterval($shift);
        if ($iv === null) {
            return [];
        }

        $date = CarbonImmutable::parse($shift->date->format('Y-m-d'));
        $weekStart = $date->startOfWeek();
        $weekEnd = $date->endOfWeek();

        $weekShifts = ScheduledShift::query()
            ->where('user_id', $shift->user_id)
            ->where('status', '!=', ScheduledShift::STATUS_CANCELLED)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->when($shift->id, fn($q) => $q->where('id', '!=', $shift->id))
            ->with('shiftType')
            ->get();

        $hours = $this->durationHours($shift);
        /** @var ScheduledShift $s */
        foreach ($weekShifts as $s) {
            $hours += $this->durationHours($s);
        }

        if ($hours > $maxH) {
            return [
                new ComplianceViolation(
                    code: 'max_weekly_hours',
                    severity: ComplianceViolation::SEVERITY_WARNING,
                    message: __('Wochenarbeitszeit :h h (KW :w) überschreitet Maximum :max h.', [
                        'h' => number_format($hours, 1, ',', ''),
                        'w' => $weekStart->isoFormat('W'),
                        'max' => $maxH,
                    ]),
                    relatedShiftIds: $weekShifts->pluck('id')->map(fn($id) => (int) $id)->all(),
                    context: ['hours' => $hours, 'max' => $maxH],
                ),
            ];
        }

        return [];
    }
}
