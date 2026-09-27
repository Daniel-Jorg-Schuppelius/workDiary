<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisBcmReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Crisis;

use App\Models\Crisis\{CrisisAction, CrisisBusinessProcess, CrisisCase, CrisisExercise};
use Carbon\CarbonImmutable;

/**
 * BCM-Auswertung nach ISO 22301 (MVP-944): Übungen und Wirksamkeit,
 * fällige Übungen, offene und überfällige Maßnahmen, Nachbetrachtungen
 * beendeter Krisen und Stand des BIA-Registers. Kennzahlen, keine
 * Zertifizierungsaussage.
 */
final class CrisisBcmReportBuilder {
    /**
     * @return array{period_from: CarbonImmutable, exercises: int, effectiveness: array<string, int>, exercises_due: int, actions_open: int, actions_overdue: int, cases_ended: int, cases_without_review: int, processes: int, by_criticality: array<string, int>, processes_without_rto: int, processes_review_due: int}
     */
    public function build(): array {
        $now = CarbonImmutable::now();
        $from = $now->subYear();
        $exercises = CrisisExercise::query()->whereNotNull('exercised_at')->where('exercised_at', '>=', $from)->get(['effectiveness']);
        $ended = CrisisCase::query()->whereIn('status', ['all_clear', 'post_review', 'closed'])->withCount('review')->get(['id']);
        $processes = CrisisBusinessProcess::query()->where('is_active', true)->get(['criticality', 'rto_hours', 'review_due_on']);

        return [
            'period_from' => $from,
            'exercises' => $exercises->count(),
            'effectiveness' => $exercises->countBy(static fn (CrisisExercise $e): string => (string) ($e->effectiveness ?? 'open'))->all(),
            'exercises_due' => CrisisExercise::query()->whereNotNull('next_due_on')->where('next_due_on', '<', $now->toDateString())->count(),
            'actions_open' => CrisisAction::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'actions_overdue' => CrisisAction::query()->whereIn('status', ['open', 'in_progress'])->whereNotNull('due_at')->where('due_at', '<', $now)->count(),
            'cases_ended' => $ended->count(),
            'cases_without_review' => $ended->where('review_count', 0)->count(),
            'processes' => $processes->count(),
            'by_criticality' => $processes->countBy(static fn (CrisisBusinessProcess $p): string => $p->criticality->value)->all(),
            'processes_without_rto' => $processes->whereNull('rto_hours')->count(),
            'processes_review_due' => $processes->filter(static fn (CrisisBusinessProcess $p): bool => $p->review_due_on !== null && $p->review_due_on->isPast())->count(),
        ];
    }
}
