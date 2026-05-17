<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RestPeriodRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance\Rules;

use App\Models\ScheduledShift;
use App\Services\Compliance\ComplianceRule;
use App\Services\Compliance\ComplianceViolation;
use App\Services\Compliance\ResolvesShiftTiming;

/** ArbZG §5: Mindestruhezeit (Standard 11h) zwischen zwei Schichten. */
final class RestPeriodRule implements ComplianceRule
{
    use ResolvesShiftTiming;

    public function key(): string
    {
        return 'rest_period';
    }

    public function check(ScheduledShift $shift, array $settings): array
    {
        $iv = $this->resolveInterval($shift);
        if ($iv === null) {
            return [];
        }
        [$start, $end] = $iv;
        $minRest = (int) $settings['min_rest_hours'];

        $candidates = ScheduledShift::query()
            ->where('user_id', $shift->user_id)
            ->where('status', '!=', ScheduledShift::STATUS_CANCELLED)
            ->whereBetween('date', [
                $start->copy()->subDays(2)->toDateString(),
                $end->copy()->addDays(2)->toDateString(),
            ])
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

            // Pausenzeit zwischen Ende der einen und Start der anderen.
            if ($oe->lessThanOrEqualTo($start)) {
                $gap = abs($oe->diffInMinutes($start)) / 60.0;
            } elseif ($end->lessThanOrEqualTo($os)) {
                $gap = abs($end->diffInMinutes($os)) / 60.0;
            } else {
                continue; // Überlapp → andere Regel
            }

            if ($gap < $minRest) {
                $violations[] = new ComplianceViolation(
                    code: 'rest_period',
                    severity: ComplianceViolation::SEVERITY_ERROR,
                    message: __('Ruhezeit nur :gh h (Mindest :mh h) zur Schicht am :date.', [
                        'gh' => number_format($gap, 1, ',', ''),
                        'mh' => $minRest,
                        'date' => $os->format('d.m.Y H:i'),
                    ]),
                    relatedShiftIds: [(int) $other->id],
                    context: ['gap_hours' => $gap, 'min_rest_hours' => $minRest],
                );
            }
        }

        return $violations;
    }
}
