<?php

declare(strict_types=1);

namespace App\Services\Compliance\Rules;

use App\Models\ScheduledShift;
use App\Services\Compliance\ComplianceRule;
use App\Services\Compliance\ComplianceViolation;
use App\Services\Compliance\ResolvesShiftTiming;

/** Erkennt zeitlich überlappende Schichten desselben Mitarbeiters. */
final class OverlapRule implements ComplianceRule
{
    use ResolvesShiftTiming;

    public function key(): string
    {
        return 'overlap';
    }

    public function check(ScheduledShift $shift, array $settings): array
    {
        $iv = $this->resolveInterval($shift);
        if ($iv === null) {
            return [];
        }
        [$start, $end] = $iv;

        $candidates = ScheduledShift::query()
            ->where('user_id', $shift->user_id)
            ->where('status', '!=', ScheduledShift::STATUS_CANCELLED)
            ->whereBetween('date', [$start->copy()->subDay()->toDateString(), $end->copy()->addDay()->toDateString()])
            ->when($shift->id, fn ($q) => $q->where('id', '!=', $shift->id))
            ->with('shiftType')
            ->get();

        $violations = [];
        /** @var ScheduledShift $other */
        foreach ($candidates as $other) {
            $oi = $this->resolveInterval($other);
            if ($oi === null) {
                continue;
            }
            [$os, $oe] = $oi;
            // Überlapp: start < oe && os < end
            if ($start->lessThan($oe) && $os->lessThan($end)) {
                $violations[] = new ComplianceViolation(
                    code: 'overlap',
                    severity: ComplianceViolation::SEVERITY_ERROR,
                    message: __('Schicht überschneidet sich mit einer anderen Schicht des Mitarbeiters am :date.', [
                        'date' => $os->format('d.m.Y H:i'),
                    ]),
                    relatedShiftIds: [(int) $other->id],
                );
            }
        }

        return $violations;
    }
}
