<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\Attendance;
use App\Services\Timekeeping\BreakRuleEvaluator;

/**
 * Stempel-Ableitungen aus dem früheren saving-Hook (Vollscan 2026-08-23,
 * F14): Kalendertag in Anzeige-Zeitzone, ArbZG-Mindestpausen, Netto-Dauer
 * und Statuswechsel Open→Closed — 1:1 verschoben.
 */
class AttendanceObserver {
    public function saving(Attendance $a): void {
        if (! $a->date && $a->started_at) {
            // Kalendertag in der Anzeige-Zeitzone, nicht UTC (23:30 lokal sonst auf Folgetag); started_at bleibt UTC.
            $a->date = $a->started_at->copy()->setTimezone(\App\Support\Tz::current())->startOfDay();
        }
        if ($a->started_at && $a->ended_at) {
            // Gesetzliche Mindestpausen (ArbZG §4) in break_minutes_auto ergänzen, bevor die Netto-Dauer folgt.
            $eval = app(BreakRuleEvaluator::class);
            if ($eval->autoApplyEnabled()) {
                $eval->applyMissingBreak($a);
            }

            $gross = (int) $a->started_at->diffInMinutes($a->ended_at, false);
            $breaks = (int) ($a->break_minutes_auto ?? 0)
                + (int) ($a->break_minutes_manual ?? 0);
            $a->duration_minutes = max(0, $gross - $breaks);
            if ($a->status === AttendanceStatus::Open) {
                $a->status = AttendanceStatus::Closed;
            }
        } else {
            $a->duration_minutes = 0;
            if (! $a->status) {
                $a->status = AttendanceStatus::Open;
            }
        }
    }
}
