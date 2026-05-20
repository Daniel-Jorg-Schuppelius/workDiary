<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BreakRuleEvaluator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Timekeeping;

use App\Models\Attendance;

/**
 * Evaluates statutory break requirements (German ArbZG §4 by default,
 * configurable via `timesheet.breaks.rules`).
 *
 * A rule entry has the shape `['after_minutes' => int, 'required_minutes' => int]`
 * and means: as soon as the gross working time exceeds `after_minutes`, at
 * least `required_minutes` of cumulative breaks must be taken.
 */
class BreakRuleEvaluator {
    /**
     * @return array<int, array{after_minutes: int, required_minutes: int}>
     */
    public function rules(): array {
        $raw = (array) config('timesheet.breaks.rules', []);
        $rules = [];
        foreach ($raw as $r) {
            if (! is_array($r) || ! isset($r['after_minutes'], $r['required_minutes'])) {
                continue;
            }
            $rules[] = [
                'after_minutes' => (int) $r['after_minutes'],
                'required_minutes' => (int) $r['required_minutes'],
            ];
        }
        usort($rules, static fn($a, $b) => $a['after_minutes'] <=> $b['after_minutes']);

        return $rules;
    }

    /**
     * Minimum break minutes required for a given gross working time.
     */
    public function requiredMinutes(int $grossMinutes): int {
        $required = 0;
        foreach ($this->rules() as $rule) {
            if ($grossMinutes > $rule['after_minutes']) {
                $required = max($required, $rule['required_minutes']);
            }
        }

        return $required;
    }

    /**
     * Difference between required and already recorded breaks (>= 0).
     */
    public function missingMinutes(Attendance $attendance): int {
        if (! $attendance->started_at || ! $attendance->ended_at) {
            return 0;
        }
        $gross = (int) $attendance->started_at->diffInMinutes($attendance->ended_at, false);
        if ($gross <= 0) {
            return 0;
        }
        $taken = (int) ($attendance->break_minutes_auto ?? 0)
            + (int) ($attendance->break_minutes_manual ?? 0);

        return max(0, $this->requiredMinutes($gross) - $taken);
    }

    /**
     * Mutates the given Attendance so the statutory minimum is met by
     * topping up `break_minutes_auto`. Returns the number of minutes added.
     */
    public function applyMissingBreak(Attendance $attendance): int {
        $missing = $this->missingMinutes($attendance);
        if ($missing <= 0) {
            return 0;
        }
        $attendance->break_minutes_auto = (int) ($attendance->break_minutes_auto ?? 0) + $missing;

        return $missing;
    }

    public function autoApplyEnabled(): bool {
        return (bool) config('timesheet.breaks.auto_apply', true);
    }
}
