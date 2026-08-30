<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCoursePortabilityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\{LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningCourse, LearningQuestion, LearningQuiz, LearningSection, LearningUnit};
use App\Models\{Organization, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kurs-Export und -Import (Feature 149, MVP-748).
 *
 * **Kein Lock-in:** ein Kurs muss das Haus verlassen können. Das Format ist
 * bewusst schlicht und dokumentiert — Struktur, Inhaltsblöcke und Fragen als
 * JSON.
 *
 * Nicht exportiert werden **Nachweise und Personendaten**: Einschreibungen,
 * Versuche, Zertifikate. Ein Kurs ist Lehrmaterial; wer ihn mitnimmt, nimmt
 * nicht die Nachweise anderer Leute mit.
 */
class LearningCoursePortabilityService {
    /** Formatversion — ein Import prüft sie, statt blind zu raten. */
    public const FORMAT_VERSION = 1;

    public function __construct(
        private readonly LearningCourseService $courses,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function export(LearningCourse $course): array {
        $course->loadMissing(['sections', 'units.quiz.questions.options']);

        return [
            'format' => 'workdiary.learning.course',
            'format_version' => self::FORMAT_VERSION,
            'course' => [
                'code' => $course->code,
                'title' => $course->title,
                'subtitle' => $course->subtitle,
                'description' => $course->description,
                'objectives' => $course->objectives,
                'language' => $course->language,
                'duration_minutes' => $course->duration_minutes,
                'validity_months' => $course->validity_months,
                'points' => $course->points,
                'time_policy' => $course->time_policy->value,
                'instruction_suitability' => $course->instruction_suitability->value,
                'sequential' => $course->sequential,
            ],
            'sections' => $course->sections->map(static fn (LearningSection $s): array => [
                'key' => 's' . $s->id,
                'title' => $s->title,
                'description' => $s->description,
                'position' => $s->position,
            ])->values()->all(),
            'units' => $course->units->map(fn (LearningUnit $u): array => [
                'section_key' => $u->learning_section_id !== null ? 's' . $u->learning_section_id : null,
                'title' => $u->title,
                'kind' => $u->kind->value,
                'position' => $u->position,
                'is_mandatory' => $u->is_mandatory,
                'points' => $u->points,
                'duration_minutes' => $u->duration_minutes,
                'blocks' => $u->blocks(),
                'quiz' => $this->exportQuiz($u->quiz),
            ])->values()->all(),
        ];
    }

    /**
     * Import als **Entwurf** in die Zielorganisation. Ein importierter Kurs
     * ist nie automatisch freigegeben — jemand muss ihn ansehen.
     *
     * @param  array<string, mixed>  $payload
     */
    public function import(Organization $organization, array $payload, ?User $actor = null): LearningCourse {
        if (($payload['format'] ?? null) !== 'workdiary.learning.course') {
            throw ValidationException::withMessages([
                'file' => (string) __('learning.errors.import_format'),
            ]);
        }

        if ((int) ($payload['format_version'] ?? 0) > self::FORMAT_VERSION) {
            throw ValidationException::withMessages([
                'file' => (string) __('learning.errors.import_version'),
            ]);
        }

        $courseData = $payload['course'] ?? [];

        if (! is_array($courseData) || trim((string) ($courseData['title'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'file' => (string) __('learning.errors.import_incomplete'),
            ]);
        }

        return DB::transaction(function () use ($organization, $payload, $courseData, $actor): LearningCourse {
            $course = $this->courses->createCourse($organization, $actor, [
                'title' => $courseData['title'],
                // Der Code wird neu vergeben: er ist je Organisation
                // eindeutig und gehört nicht zum Lehrmaterial.
                'subtitle' => $courseData['subtitle'] ?? null,
                'description' => $courseData['description'] ?? null,
                'objectives' => $courseData['objectives'] ?? null,
                'language' => $courseData['language'] ?? null,
                'duration_minutes' => $courseData['duration_minutes'] ?? null,
                'validity_months' => $courseData['validity_months'] ?? null,
                'points' => $courseData['points'] ?? 0,
                'time_policy' => $courseData['time_policy'] ?? null,
                'instruction_suitability' => $courseData['instruction_suitability'] ?? null,
                'sequential' => $courseData['sequential'] ?? false,
            ]);

            $sectionMap = [];
            foreach ($payload['sections'] ?? [] as $section) {
                if (! is_array($section)) {
                    continue;
                }
                $created = $this->courses->addSection($course, [
                    'title' => $section['title'] ?? '—',
                    'description' => $section['description'] ?? null,
                    'position' => $section['position'] ?? 0,
                ]);
                $sectionMap[(string) ($section['key'] ?? '')] = $created;
            }

            foreach ($payload['units'] ?? [] as $unit) {
                if (! is_array($unit)) {
                    continue;
                }

                $created = $this->courses->addUnit($course, [
                    'title' => $unit['title'] ?? '—',
                    'kind' => LearningUnitKind::tryFrom((string) ($unit['kind'] ?? ''))->value ?? LearningUnitKind::Content->value,
                    'section' => $sectionMap[(string) ($unit['section_key'] ?? '')] ?? null,
                    'position' => $unit['position'] ?? 0,
                    'is_mandatory' => $unit['is_mandatory'] ?? true,
                    'points' => $unit['points'] ?? 0,
                    'duration_minutes' => $unit['duration_minutes'] ?? null,
                    'content' => is_array($unit['blocks'] ?? null) ? $unit['blocks'] : null,
                ]);

                if (is_array($unit['quiz'] ?? null)) {
                    $this->importQuiz($created, $unit['quiz']);
                }
            }

            return $course->refresh();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function exportQuiz(?LearningQuiz $quiz): ?array {
        if ($quiz === null) {
            return null;
        }

        return [
            'title' => $quiz->title,
            'description' => $quiz->description,
            'pass_percent' => $quiz->pass_percent,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'max_attempts' => $quiz->max_attempts,
            'retry_wait_hours' => $quiz->retry_wait_hours,
            'questions_per_attempt' => $quiz->questions_per_attempt,
            'shuffle_questions' => $quiz->shuffle_questions,
            'shuffle_answers' => $quiz->shuffle_answers,
            'feedback_mode' => $quiz->feedback_mode->value,
            'show_solutions' => $quiz->show_solutions,
            'questions' => array_values($quiz->questions->map(static fn (LearningQuestion $q): array => [
                'kind' => $q->kind->value,
                'prompt' => $q->prompt,
                'explanation' => $q->explanation,
                'points' => $q->points,
                'position' => $q->position,
                'settings' => $q->settings,
                'options' => array_values($q->options->map(static fn ($o): array => [
                    'label' => $o->label,
                    'is_correct' => $o->is_correct,
                    'position' => $o->position,
                    'match_key' => $o->match_key,
                ])->all()),
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importQuiz(LearningUnit $unit, array $data): void {
        $quiz = LearningQuiz::query()->create([
            'organization_id' => $unit->organization_id,
            'learning_unit_id' => $unit->id,
            'title' => $data['title'] ?? $unit->title,
            'description' => $data['description'] ?? null,
            'pass_percent' => (int) ($data['pass_percent'] ?? 80),
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'max_attempts' => (int) ($data['max_attempts'] ?? 3),
            'retry_wait_hours' => (int) ($data['retry_wait_hours'] ?? 0),
            'questions_per_attempt' => $data['questions_per_attempt'] ?? null,
            'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? true),
            'shuffle_answers' => (bool) ($data['shuffle_answers'] ?? true),
            'feedback_mode' => $data['feedback_mode'] ?? 'end',
            'show_solutions' => (bool) ($data['show_solutions'] ?? false),
        ]);

        foreach ($data['questions'] ?? [] as $index => $question) {
            if (! is_array($question)) {
                continue;
            }

            $created = LearningQuestion::query()->create([
                'organization_id' => $unit->organization_id,
                'learning_quiz_id' => $quiz->id,
                'kind' => LearningQuestionKind::tryFrom((string) ($question['kind'] ?? ''))->value ?? LearningQuestionKind::Single->value,
                'prompt' => $question['prompt'] ?? '—',
                'explanation' => $question['explanation'] ?? null,
                'points' => (int) ($question['points'] ?? 1),
                'position' => (int) ($question['position'] ?? $index + 1),
                'settings' => is_array($question['settings'] ?? null) ? $question['settings'] : null,
            ]);

            foreach ($question['options'] ?? [] as $position => $option) {
                if (! is_array($option)) {
                    continue;
                }

                $created->options()->create([
                    'organization_id' => $unit->organization_id,
                    'label' => $option['label'] ?? '—',
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'position' => (int) ($option['position'] ?? $position + 1),
                    'match_key' => $option['match_key'] ?? null,
                ]);
            }
        }
    }
}
