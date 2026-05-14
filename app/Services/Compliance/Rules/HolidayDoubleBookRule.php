<?php

declare(strict_types=1);

namespace App\Services\Compliance\Rules;

use App\Models\Holiday;
use App\Models\ScheduledShift;
use App\Services\Compliance\ComplianceRule;
use App\Services\Compliance\ComplianceViolation;

/** Warnt, wenn an einem Feiertag eine Schicht geplant wird (organisationsbezogen). */
final class HolidayDoubleBookRule implements ComplianceRule
{
    public function key(): string
    {
        return 'holiday_double_book';
    }

    public function check(ScheduledShift $shift, array $settings): array
    {
        $year = (int) $shift->date->format('Y');
        $iso = $shift->date->format('Y-m-d');

        $holidays = Holiday::query()->get();
        /** @var Holiday $holiday */
        foreach ($holidays as $holiday) {
            $dates = $holiday->resolveForYear($year);
            if (in_array($iso, $dates, true)) {
                return [
                    new ComplianceViolation(
                        code: 'holiday_double_book',
                        severity: ComplianceViolation::SEVERITY_WARNING,
                        message: __('Schicht liegt auf Feiertag „:name" (:date).', [
                            'name' => $holiday->name ?? __('Feiertag'),
                            'date' => $shift->date->format('d.m.Y'),
                        ]),
                        relatedShiftIds: [],
                        context: ['holiday_id' => $holiday->id],
                    ),
                ];
            }
        }

        return [];
    }
}
