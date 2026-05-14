<?php

declare(strict_types=1);

namespace App\Services\Compliance\Rules;

use App\Models\ScheduledShift;
use App\Services\Compliance\ComplianceRule;
use App\Services\Compliance\ComplianceViolation;
use App\Services\Compliance\ResolvesShiftTiming;

/** ArbZG §3: max. Tagesarbeitszeit (Standard 10h, ggf. erweitert auf 8h Durchschnitt). */
final class MaxDailyHoursRule implements ComplianceRule
{
    use ResolvesShiftTiming;

    public function key(): string
    {
        return 'max_daily_hours';
    }

    public function check(ScheduledShift $shift, array $settings): array
    {
        $maxH = (float) $settings['max_hours_day'];
        $iv = $this->resolveInterval($shift);
        if ($iv === null) {
            return [];
        }
        [$start, $end] = $iv;
        $dateStr = $shift->date->format('Y-m-d');

        // Gesamtstunden des Mitarbeiters an diesem Kalendertag (ohne abgesagte).
        $sameDay = ScheduledShift::query()
            ->where('user_id', $shift->user_id)
            ->where('status', '!=', ScheduledShift::STATUS_CANCELLED)
            ->whereDate('date', $dateStr)
            ->when($shift->id, fn ($q) => $q->where('id', '!=', $shift->id))
            ->with('shiftType')
            ->get();

        $hours = $this->durationHours($shift);
        /** @var ScheduledShift $s */
        foreach ($sameDay as $s) {
            $hours += $this->durationHours($s);
        }

        if ($hours > $maxH) {
            return [
                new ComplianceViolation(
                    code: 'max_daily_hours',
                    severity: ComplianceViolation::SEVERITY_ERROR,
                    message: __('Tagesarbeitszeit :h h überschreitet Maximum :max h.', [
                        'h' => number_format($hours, 1, ',', ''),
                        'max' => $maxH,
                    ]),
                    relatedShiftIds: $sameDay->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    context: ['hours' => $hours, 'max' => $maxH],
                ),
            ];
        }

        return [];
    }
}
