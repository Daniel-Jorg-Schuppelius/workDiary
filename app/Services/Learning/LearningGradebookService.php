<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningGradebookService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningSubmissionStatus;
use App\Models\Learning\{LearningEnrollment, LearningQuizAttempt, LearningSubmission};
use App\Models\Organization;

/**
 * Notenbuch (Feature 149, MVP-739).
 *
 * Kein eigener Datenbestand: das Gesamtergebnis wird aus den vorhandenen
 * Bestandteilen gerechnet — bester Prüfungsversuch je Prüfung plus
 * bewertete Aufgaben. Wer es speichern würde, hätte zwei Wahrheiten.
 *
 * Das **Notenschema** ist eine Organisationseinstellung
 * (`settings['learning']['grade_scale']`), keine harte Verdrahtung: 1–6,
 * A–F oder bestanden/nicht bestanden sind je nach Haus üblich.
 */
class LearningGradebookService {
    /**
     * @return array{
     *   components: list<array{kind: string, title: string, points: int, max: int, percent: int, pending: bool}>,
     *   points: int, max: int, percent: int, pending: bool, grade: string|null
     * }
     */
    public function forEnrollment(LearningEnrollment $enrollment): array {
        $components = [];
        $points = 0;
        $max = 0;
        $pending = false;

        foreach ($this->bestAttempts($enrollment) as $attempt) {
            $components[] = [
                'kind' => 'quiz',
                'title' => (string) ($attempt->quiz->title ?? ''),
                'points' => (int) $attempt->score_points,
                'max' => (int) $attempt->max_points,
                'percent' => (int) ($attempt->score_percent ?? 0),
                'pending' => $attempt->passed === null,
            ];
            $points += (int) $attempt->score_points;
            $max += (int) $attempt->max_points;
            $pending = $pending || $attempt->passed === null;
        }

        /** @var iterable<LearningSubmission> $submissions */
        $submissions = LearningSubmission::query()
            ->with('assignment')
            ->where('learning_enrollment_id', $enrollment->id)
            ->whereIn('status', [LearningSubmissionStatus::Submitted->value, LearningSubmissionStatus::Graded->value])
            ->get();

        foreach ($submissions as $submission) {
            $assignmentMax = (int) ($submission->assignment->points ?? 0);
            $isPending = $submission->status !== LearningSubmissionStatus::Graded;

            $components[] = [
                'kind' => 'assignment',
                'title' => (string) ($submission->assignment->title ?? ''),
                'points' => (int) ($submission->points_awarded ?? 0),
                'max' => $assignmentMax,
                'percent' => (int) ($submission->score_percent ?? 0),
                'pending' => $isPending,
            ];
            $points += (int) ($submission->points_awarded ?? 0);
            $max += $assignmentMax;
            $pending = $pending || $isPending;
        }

        $percent = $max > 0 ? (int) round($points / $max * 100) : 0;

        return [
            'components' => $components,
            'points' => $points,
            'max' => $max,
            'percent' => $percent,
            'pending' => $pending,
            // Solange etwas offen ist, gibt es keine Note — eine vorläufige
            // Note wäre eine Aussage, die nicht trägt.
            'grade' => $pending ? null : $this->gradeFor($enrollment->organization, $percent),
        ];
    }

    /**
     * Notenschema der Organisation; ohne Pflege bleibt die Note leer und es
     * zählt allein der Prozentwert.
     *
     * @return list<array{min_percent: int, label: string}>
     */
    public function scaleFor(?Organization $organization): array {
        $settings = $organization->settings ?? [];
        $scale = $settings['learning']['grade_scale'] ?? [];

        if (! is_array($scale)) {
            return [];
        }

        $rows = [];
        foreach ($scale as $row) {
            if (! is_array($row) || ! isset($row['min_percent'], $row['label'])) {
                continue;
            }
            $rows[] = [
                'min_percent' => (int) $row['min_percent'],
                'label' => (string) $row['label'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['min_percent'] <=> $a['min_percent']);

        return $rows;
    }

    public function gradeFor(?Organization $organization, int $percent): ?string {
        foreach ($this->scaleFor($organization) as $row) {
            if ($percent >= $row['min_percent']) {
                return $row['label'];
            }
        }

        return null;
    }

    /**
     * Bester Versuch je Prüfung — Wiederholungen sollen nicht bestrafen.
     *
     * @return list<LearningQuizAttempt>
     */
    private function bestAttempts(LearningEnrollment $enrollment): array {
        $attempts = LearningQuizAttempt::query()
            ->with('quiz')
            ->where('learning_enrollment_id', $enrollment->id)
            ->whereNotNull('submitted_at')
            ->get();

        $best = [];
        foreach ($attempts as $attempt) {
            $key = (int) $attempt->learning_quiz_id;
            $current = $best[$key] ?? null;
            if ($current === null || (int) $attempt->score_points > (int) $current->score_points) {
                $best[$key] = $attempt;
            }
        }

        return array_values($best);
    }
}
