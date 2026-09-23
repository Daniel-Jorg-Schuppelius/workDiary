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
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningGradebookComponent, LearningManualGrade, LearningQuizAttempt, LearningSubmission, LearningUnit};
use App\Models\Platform\{Organization, User};
use App\Support\CsvExport;
use CommonToolkit\Enums\Common\CSV\QuotingStyle;
use CommonToolkit\Helper\Data\CSV\StringHelper;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Notenbuch (Feature 149, MVP-739; Ausbau MVP-790).
 *
 * Kein eigener Datenbestand für das Gesamtergebnis: es wird aus den
 * vorhandenen Bestandteilen gerechnet — bester Prüfungsversuch je Prüfung
 * plus bewertete Aufgaben. Wer es speichern würde, hätte zwei Wahrheiten.
 *
 * Seit MVP-790 kann ein Kurs **Komponenten** definieren (Prüfung, Aufgabe,
 * manuelle Note) mit optionalen Gewichten: dann zählt je Komponente der
 * Prozentwert, gewichtet nach `weight_percent` (Summe 100). Ohne Gewichte
 * werden Punkte addiert; ohne Komponenten gilt der Weg von MVP-739.
 * Manuelle Noten sind additiv — die jüngste zählt, ältere bleiben Verlauf.
 *
 * Das **Notenschema** ist eine Organisationseinstellung
 * (`settings['learning']['grade_scale']`), keine harte Verdrahtung: 1–6,
 * A–F oder bestanden/nicht bestanden sind je nach Haus üblich.
 */
class LearningGradebookService {
    /**
     * @return array{
     *   components: list<array{kind: string, title: string, points: int, max: int, percent: int, pending: bool, weight: int|null, component_id: int|null}>,
     *   points: int, max: int, percent: int, pending: bool, grade: string|null, weighted: bool
     * }
     */
    public function forEnrollment(LearningEnrollment $enrollment): array {
        $course = $enrollment->course;
        $defined = $course !== null ? $this->componentsFor($course) : collect();

        return $defined->isNotEmpty()
            ? $this->fromComponents($enrollment, $defined)
            : $this->fromParts($enrollment);
    }

    /**
     * Komponenten eines Kurses in Reihenfolge.
     *
     * @return Collection<int, LearningGradebookComponent>
     */
    public function componentsFor(LearningCourse $course): Collection {
        return LearningGradebookComponent::query()
            ->with('unit')
            ->where('learning_course_id', $course->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Komponenten festlegen. Gewichte: entweder alle leer (Punkte addieren)
     * oder alle gesetzt mit Summe 100 — ein halbgewichtetes Notenbuch wäre
     * eine Aussage, die niemand nachrechnen kann.
     *
     * @param  list<array<string, mixed>>  $rows  je Zeile: kind, id?, unit_id?, title?, weight?, max_points?
     * @return Collection<int, LearningGradebookComponent>
     */
    public function saveComponents(LearningCourse $course, array $rows): Collection {
        $weights = array_map(static fn (array $r): ?int => array_key_exists('weight', $r) && $r['weight'] !== null && $r['weight'] !== '' ? (int) $r['weight'] : null, $rows);
        $set = array_filter($weights, static fn (?int $w): bool => $w !== null);
        if ($set !== [] && (count($set) !== count($rows) || array_sum($set) !== 100)) {
            throw ValidationException::withMessages([
                'components' => (string) __('learning.errors.weights_sum'),
            ]);
        }

        $units = $course->units()->with(['quiz', 'assignment'])->get()->keyBy('id');

        return DB::transaction(function () use ($course, $rows, $weights, $units): Collection {
            $keep = [];
            foreach ($rows as $position => $row) {
                $kind = (string) ($row['kind'] ?? '');
                $unit = null;
                $title = trim((string) ($row['title'] ?? ''));
                $max = null;

                if ($kind === LearningGradebookComponent::KIND_MANUAL) {
                    $max = max(1, (int) ($row['max_points'] ?? 0));
                    if ($title === '') {
                        throw ValidationException::withMessages(['components' => (string) __('learning.errors.component_title_required')]);
                    }
                } else {
                    /** @var LearningUnit|null $unit */
                    $unit = $units->get((int) ($row['unit_id'] ?? 0));
                    $matches = $unit !== null && (
                        ($kind === LearningGradebookComponent::KIND_QUIZ && $unit->quiz !== null)
                        || ($kind === LearningGradebookComponent::KIND_ASSIGNMENT && $unit->assignment !== null)
                    );
                    if (! $matches) {
                        throw ValidationException::withMessages(['components' => (string) __('learning.errors.component_unit_invalid')]);
                    }
                    $title = $title !== '' ? $title : (string) $unit->title;
                }

                $existing = isset($row['id'])
                    ? LearningGradebookComponent::query()->where('learning_course_id', $course->id)->find((int) $row['id'])
                    : ($unit !== null
                        ? LearningGradebookComponent::query()->where('learning_course_id', $course->id)->where('kind', $kind)->where('learning_unit_id', $unit->id)->first()
                        : null);

                $attributes = [
                    'organization_id' => $course->organization_id,
                    'learning_course_id' => $course->id,
                    'kind' => $kind,
                    'learning_unit_id' => $unit?->id,
                    'title' => $title,
                    'weight_percent' => $weights[$position],
                    'max_points' => $max,
                    'position' => $position + 1,
                ];
                $component = $existing !== null
                    ? tap($existing)->update($attributes)
                    : LearningGradebookComponent::query()->create($attributes);
                $keep[] = $component->id;
            }

            // Gestrichene Komponenten verschwinden samt manueller Noten —
            // das Audit der Komponente hält fest, dass es sie gab.
            LearningGradebookComponent::query()
                ->where('learning_course_id', $course->id)
                ->whereKeyNot($keep)
                ->get()
                ->each(static fn (LearningGradebookComponent $c) => $c->delete());

            return $this->componentsFor($course);
        });
    }

    /** Manuelle Note eintragen — additiv, die jüngste zählt. */
    public function recordManualGrade(
        LearningEnrollment $enrollment,
        LearningGradebookComponent $component,
        int $points,
        ?string $note = null,
        ?User $actor = null,
        ?Carbon $now = null,
    ): LearningManualGrade {
        if (! $component->isManual() || $component->learning_course_id !== $enrollment->learning_course_id) {
            throw ValidationException::withMessages(['component' => (string) __('learning.errors.component_foreign')]);
        }
        $max = max(1, (int) $component->max_points);
        if ($points < 0 || $points > $max) {
            throw ValidationException::withMessages(['points' => (string) __('learning.errors.points_exceed_max', ['max' => $max])]);
        }

        return LearningManualGrade::query()->create([
            'organization_id' => $enrollment->organization_id,
            'learning_enrollment_id' => $enrollment->id,
            'learning_gradebook_component_id' => $component->id,
            'points' => $points,
            'max_points' => $max,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'graded_by_user_id' => $actor?->id,
            'graded_at' => $now ?? Carbon::now(),
        ]);
    }

    /**
     * Kursübersicht: Lernende × Komponenten.
     *
     * @return array{components: Collection<int, LearningGradebookComponent>, rows: list<array{enrollment: LearningEnrollment, result: array<string, mixed>}>}
     */
    public function forCourse(LearningCourse $course): array {
        $enrollments = LearningEnrollment::query()
            ->with(['user', 'externalParticipant', 'course'])
            ->where('learning_course_id', $course->id)
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($enrollments as $enrollment) {
            $rows[] = ['enrollment' => $enrollment, 'result' => $this->forEnrollment($enrollment)];
        }

        return ['components' => $this->componentsFor($course), 'rows' => $rows];
    }

    /** CSV der Kursübersicht — Formel-Guard, weil Namen frei erfasst sind. */
    public function csvFor(LearningCourse $course): string {
        $matrix = $this->forCourse($course);
        $head = [(string) __('learning.field.learner'), (string) __('learning.field.status')];
        foreach ($matrix['components'] as $component) {
            $head[] = (string) $component->title;
        }
        $head[] = (string) __('learning.field.total');
        $head[] = (string) __('learning.field.grade');

        $csv = StringHelper::encodeLine($head, ',', '"', QuotingStyle::FPUTCSV) . "\n";
        foreach ($matrix['rows'] as $row) {
            $cells = [$row['enrollment']->learnerName(), $row['enrollment']->status->label()];
            $byId = collect((array) $row['result']['components'])->keyBy('component_id');
            foreach ($matrix['components'] as $component) {
                $cell = $byId->get($component->id);
                $cells[] = $cell === null ? '' : ($cell['pending'] ? '' : $cell['points'] . '/' . $cell['max']);
            }
            if ($matrix['components']->isEmpty()) {
                // Ohne Komponenten trägt die Zeile die Bestandteile nicht einzeln.
            }
            $cells[] = $row['result']['pending'] ? '' : (string) $row['result']['percent'];
            $cells[] = (string) ($row['result']['grade'] ?? '');
            $csv .= StringHelper::encodeLine(CsvExport::guardRow(array_map('strval', $cells)), ',', '"', QuotingStyle::FPUTCSV) . "\n";
        }

        return $csv;
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
     * MVP-739: alle Prüfungen und Aufgaben, Punkte addiert.
     *
     * @return array{components: list<array{kind: string, title: string, points: int, max: int, percent: int, pending: bool, weight: int|null, component_id: int|null}>, points: int, max: int, percent: int, pending: bool, grade: string|null, weighted: bool}
     */
    private function fromParts(LearningEnrollment $enrollment): array {
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
                'weight' => null,
                'component_id' => null,
            ];
            $points += (int) $attempt->score_points;
            $max += (int) $attempt->max_points;
            $pending = $pending || $attempt->passed === null;
        }

        foreach ($this->submissions($enrollment) as $submission) {
            $assignmentMax = (int) ($submission->assignment->points ?? 0);
            $isPending = $submission->status !== LearningSubmissionStatus::Graded;

            $components[] = [
                'kind' => 'assignment',
                'title' => (string) ($submission->assignment->title ?? ''),
                'points' => (int) ($submission->points_awarded ?? 0),
                'max' => $assignmentMax,
                'percent' => (int) ($submission->score_percent ?? 0),
                'pending' => $isPending,
                'weight' => null,
                'component_id' => null,
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
            'weighted' => false,
        ];
    }

    /**
     * MVP-790: definierte Komponenten, optional gewichtet.
     *
     * @param  Collection<int, LearningGradebookComponent>  $defined
     * @return array{components: list<array{kind: string, title: string, points: int, max: int, percent: int, pending: bool, weight: int|null, component_id: int|null}>, points: int, max: int, percent: int, pending: bool, grade: string|null, weighted: bool}
     */
    private function fromComponents(LearningEnrollment $enrollment, Collection $defined): array {
        $attemptsByQuiz = collect($this->bestAttempts($enrollment))->keyBy('learning_quiz_id');
        $submissionsByAssignment = $this->submissions($enrollment)->keyBy('learning_assignment_id');
        $manualByComponent = LearningManualGrade::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->orderByDesc('graded_at')
            ->orderByDesc('id')
            ->get()
            ->unique('learning_gradebook_component_id')
            ->keyBy('learning_gradebook_component_id');

        $weighted = $defined->every(static fn (LearningGradebookComponent $c): bool => $c->weight_percent !== null);
        $components = [];
        $points = 0;
        $max = 0;
        $weightedPercent = 0.0;
        $pending = false;

        foreach ($defined as $component) {
            $cell = ['points' => 0, 'max' => 0, 'percent' => 0, 'pending' => true];

            if ($component->kind === LearningGradebookComponent::KIND_QUIZ) {
                $attempt = $component->unit?->quiz !== null ? $attemptsByQuiz->get($component->unit->quiz->id) : null;
                if ($attempt !== null) {
                    $cell = ['points' => (int) $attempt->score_points, 'max' => (int) $attempt->max_points, 'percent' => (int) ($attempt->score_percent ?? 0), 'pending' => $attempt->passed === null];
                }
            } elseif ($component->kind === LearningGradebookComponent::KIND_ASSIGNMENT) {
                $submission = $component->unit?->assignment !== null ? $submissionsByAssignment->get($component->unit->assignment->id) : null;
                if ($submission !== null) {
                    $cell = [
                        'points' => (int) ($submission->points_awarded ?? 0),
                        'max' => (int) ($submission->assignment->points ?? 0),
                        'percent' => (int) ($submission->score_percent ?? 0),
                        'pending' => $submission->status !== LearningSubmissionStatus::Graded,
                    ];
                }
            } else {
                $grade = $manualByComponent->get($component->id);
                if ($grade !== null) {
                    $cell = [
                        'points' => (int) $grade->points,
                        'max' => (int) $grade->max_points,
                        'percent' => (int) round($grade->points / max(1, $grade->max_points) * 100),
                        'pending' => false,
                    ];
                }
            }

            $components[] = array_merge($cell, [
                'kind' => $component->kind,
                'title' => (string) $component->title,
                'weight' => $component->weight_percent,
                'component_id' => (int) $component->id,
            ]);
            $points += $cell['points'];
            $max += $cell['max'];
            $weightedPercent += $cell['percent'] * ((int) $component->weight_percent) / 100;
            $pending = $pending || $cell['pending'];
        }

        $percent = $weighted
            ? (int) round($weightedPercent)
            : ($max > 0 ? (int) round($points / $max * 100) : 0);

        return [
            'components' => $components,
            'points' => $points,
            'max' => $max,
            'percent' => $percent,
            'pending' => $pending,
            'grade' => $pending ? null : $this->gradeFor($enrollment->organization, $percent),
            'weighted' => $weighted,
        ];
    }

    /** @return Collection<int, LearningSubmission> */
    private function submissions(LearningEnrollment $enrollment): Collection {
        return LearningSubmission::query()
            ->with('assignment')
            ->where('learning_enrollment_id', $enrollment->id)
            ->whereIn('status', [LearningSubmissionStatus::Submitted->value, LearningSubmissionStatus::Graded->value])
            ->orderBy('id')
            ->get();
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
