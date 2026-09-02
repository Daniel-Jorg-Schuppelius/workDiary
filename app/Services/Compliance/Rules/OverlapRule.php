<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OverlapRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance\Rules;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\ScheduledShift;
use App\Services\Compliance\{ComplianceRule, ComplianceViolation, ResolvesShiftTiming};
use App\Support\Query\DateRange;

/** Erkennt zeitlich überlappende Schichten desselben Mitarbeiters. */
final class OverlapRule implements ComplianceRule {
    use ResolvesShiftTiming;

    public function key(): string {
        return 'overlap';
    }

    public function check(ScheduledShift $shift, array $settings): array {
        $iv = $this->resolveInterval($shift);
        if ($iv === null) {
            return [];
        }
        [$start, $end] = $iv;

        $candidates = ScheduledShift::query()
            ->where('user_id', $shift->user_id)
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->whereBetween('date', DateRange::days($start->copy()->subDay(), $end->copy()->addDay()))
            ->when($shift->id, fn($q) => $q->where('id', '!=', $shift->id))
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
