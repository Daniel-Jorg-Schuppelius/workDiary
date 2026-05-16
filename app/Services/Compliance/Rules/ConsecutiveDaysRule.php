<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConsecutiveDaysRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance\Rules;

use App\Models\ScheduledShift;
use App\Services\Compliance\ComplianceRule;
use App\Services\Compliance\ComplianceViolation;
use Carbon\CarbonImmutable;

/** Max. Anzahl aufeinanderfolgender Arbeitstage (default 6). */
final class ConsecutiveDaysRule implements ComplianceRule {
    public function key(): string {
        return 'consecutive_days';
    }

    public function check(ScheduledShift $shift, array $settings): array {
        $max = (int) $settings['max_consecutive_days'];
        $date = CarbonImmutable::parse($shift->date->format('Y-m-d'));

        // Alle Schichten des Mitarbeiters in einem +/- 14-Tage-Fenster.
        $rows = ScheduledShift::query()
            ->where('user_id', $shift->user_id)
            ->where('status', '!=', ScheduledShift::STATUS_CANCELLED)
            ->whereBetween('date', [$date->subDays(14)->toDateString(), $date->addDays(14)->toDateString()])
            ->when($shift->id, fn($q) => $q->where('id', '!=', $shift->id))
            ->get(['id', 'date']);

        $dates = collect([$date->toDateString()])
            ->merge($rows->pluck('date')->map(fn($d) => CarbonImmutable::parse($d->format('Y-m-d'))->toDateString()))
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Längste lückenlose Serie, die das aktuelle Datum enthält.
        $cur = 1;
        $streak = 1;
        $start = $date;
        for ($i = 1; $i <= 14; $i++) {
            $d = $date->subDays($i)->toDateString();
            if (in_array($d, $dates, true)) {
                $streak++;
                $start = $date->subDays($i);
            } else {
                break;
            }
        }
        for ($i = 1; $i <= 14; $i++) {
            $d = $date->addDays($i)->toDateString();
            if (in_array($d, $dates, true)) {
                $streak++;
            } else {
                break;
            }
        }

        if ($streak > $max) {
            return [
                new ComplianceViolation(
                    code: 'consecutive_days',
                    severity: ComplianceViolation::SEVERITY_WARNING,
                    message: __(':d aufeinanderfolgende Arbeitstage (Maximum :max) ab :start.', [
                        'd' => $streak,
                        'max' => $max,
                        'start' => $start->format('d.m.Y'),
                    ]),
                    relatedShiftIds: $rows->pluck('id')->map(fn($id) => (int) $id)->all(),
                    context: ['streak' => $streak, 'max' => $max],
                ),
            ];
        }

        return [];
    }
}
