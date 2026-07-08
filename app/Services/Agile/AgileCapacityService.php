<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileCapacityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Agile;

use App\Models\{Project, Vacation, WorkSchedule};
use Illuminate\Support\Carbon;

/**
 * Kapazitäts-Snapshot (Feature 064, P10/MVP-148): Projektmitglieder
 * (assignableUsers) × Arbeitszeitmodell (WorkSchedule.weekly_minutes,
 * gültig im Sprintfenster) − genehmigte Urlaube ± manuelle Korrektur mit
 * Pflichtbegründung. Krankheit ist beim Start naturgemäß unbekannt und
 * fließt bewusst nicht ein.
 */
class AgileCapacityService {
    /**
     * @return array{users: array<int, array<string, mixed>>, base_hours: float, absence_hours: float, adjustment_hours: float, adjustment_reason: string|null, total_hours: float, from: string, to: string}
     */
    public function snapshot(Project $project, Carbon $from, Carbon $to, float $adjustmentHours = 0.0, ?string $adjustmentReason = null): array {
        if ($adjustmentHours !== 0.0 && trim((string) $adjustmentReason) === '') {
            throw new \InvalidArgumentException((string) __('Eine Kapazitätskorrektur braucht eine Begründung.'));
        }

        $calendarDays = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->endOfDay()) + 1;
        $weeks = $calendarDays / 7;

        $rows = [];
        $baseHours = 0.0;
        $absenceHours = 0.0;
        foreach ($project->assignableUsers() as $user) {
            $schedule = WorkSchedule::query()
                ->where('user_id', $user->id)
                ->whereDate('valid_from', '<=', $to)
                ->where(fn($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $from))
                ->orderByDesc('valid_from')
                ->first();
            $weeklyMinutes = (int) ($schedule->weekly_minutes ?? 0);
            $userBase = round($weeklyMinutes / 60 * $weeks, 1);

            // Genehmigte Urlaube im Fenster: Überlapp-Tage × Tagessoll (5-Tage-Woche).
            $dailyHours = $weeklyMinutes / 60 / 5;
            $userAbsence = 0.0;
            $vacations = Vacation::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $to)
                ->whereDate('end_date', '>=', $from)
                ->get();
            foreach ($vacations as $vacation) {
                $overlapStart = $vacation->start_date->copy()->max($from);
                $overlapEnd = $vacation->end_date->copy()->min($to);
                $days = (int) $overlapStart->diffInDays($overlapEnd) + 1;
                $userAbsence += round($days * $dailyHours, 1);
            }

            $rows[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'base_hours' => $userBase,
                'absence_hours' => $userAbsence,
            ];
            $baseHours += $userBase;
            $absenceHours += $userAbsence;
        }

        return [
            'users' => $rows,
            'base_hours' => round($baseHours, 1),
            'absence_hours' => round($absenceHours, 1),
            'adjustment_hours' => $adjustmentHours,
            'adjustment_reason' => $adjustmentHours !== 0.0 ? trim((string) $adjustmentReason) : null,
            'total_hours' => round($baseHours - $absenceHours + $adjustmentHours, 1),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }
}
