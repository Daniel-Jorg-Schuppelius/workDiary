<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\{LearningAudience, LearningCourseStatus, LearningTimePolicy, LearningUnitKind};
use App\Models\Learning\{LearningCourse, LearningCourseVersion, LearningSection, LearningUnit};
use App\Models\{Organization, User};
use App\Services\Training\TrainingCatalogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Einzige Schreibstelle für Struktur und Freigabe eines Lernkurses
 * (Feature 149, MVP-735).
 *
 * Zwei Regeln, die hier durchgesetzt werden und nirgendwo sonst:
 *  1. Ein freigegebener Kurs ist inhaltlich gesperrt — Korrektur nur über
 *     eine Folgeversion. Sonst wäre ein Nachweis nach einer Kursänderung
 *     nicht mehr erklärbar.
 *  2. Die Freigabe schreibt die Kursversion in Feature 145 (einzige
 *     Schreibrichtung), damit Soll und Nachweis dieselbe Versionsnummer
 *     tragen.
 */
class LearningCourseService {
    public function __construct(private readonly TrainingCatalogService $trainingCatalog) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCourse(Organization $organization, ?User $actor, array $attributes): LearningCourse {
        return DB::transaction(function () use ($organization, $actor, $attributes): LearningCourse {
            $course = LearningCourse::query()->create([
                'organization_id' => $organization->id,
                'code' => $this->resolveCode($organization, $attributes),
                'title' => (string) $attributes['title'],
                'subtitle' => $attributes['subtitle'] ?? null,
                'description' => $attributes['description'] ?? null,
                'objectives' => $attributes['objectives'] ?? null,
                'language' => (string) ($attributes['language'] ?? config('app.locale', 'de')),
                'status' => LearningCourseStatus::Draft->value,
                'audiences' => $this->normalizeAudiences($attributes['audiences'] ?? [LearningAudience::Internal->value]),
                'access_kind' => $attributes['access_kind'] ?? 'enrolled',
                'training_course_id' => $attributes['training_course_id'] ?? null,
                'qualification_id' => $attributes['qualification_id'] ?? null,
                // Geräteeinweisung (MVP-740): Zeiger auf das konkrete Gerät.
                'asset_id' => $attributes['asset_id'] ?? null,
                'competency_id' => $attributes['competency_id'] ?? null,
                'competency_level' => $attributes['competency_level'] ?? null,
                'article_id' => $attributes['article_id'] ?? null,
                'owner_user_id' => $attributes['owner_user_id'] ?? $actor?->id,
                'duration_minutes' => $attributes['duration_minutes'] ?? null,
                'validity_months' => $attributes['validity_months'] ?? null,
                'points' => max(0, (int) ($attributes['points'] ?? 0)),
                'time_policy' => $attributes['time_policy'] ?? LearningTimePolicy::WorkTimeRequired->value,
                'instruction_suitability' => $attributes['instruction_suitability'] ?? 'supplementary',
                'certificate_enabled' => (bool) ($attributes['certificate_enabled'] ?? false),
                'creates_instruction_proof' => (bool) ($attributes['creates_instruction_proof'] ?? false),
                'access_days' => $attributes['access_days'] ?? null,
                'sequential' => (bool) ($attributes['sequential'] ?? false),
            ]);

            $this->guardTimePolicy($course);

            return $course->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCourse(LearningCourse $course, array $attributes): LearningCourse {
        // Stammdaten bleiben pflegbar (Titel, Verantwortlicher, Zielgruppen);
        // gesperrt ist der INHALT, siehe guardEditable().
        $course->update([
            'title' => $attributes['title'] ?? $course->title,
            'subtitle' => array_key_exists('subtitle', $attributes) ? $attributes['subtitle'] : $course->subtitle,
            'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $course->description,
            'objectives' => array_key_exists('objectives', $attributes) ? $attributes['objectives'] : $course->objectives,
            'language' => $attributes['language'] ?? $course->language,
            'audiences' => array_key_exists('audiences', $attributes) ? $this->normalizeAudiences($attributes['audiences']) : $course->audiences,
            'access_kind' => $attributes['access_kind'] ?? $course->access_kind,
            'training_course_id' => array_key_exists('training_course_id', $attributes) ? $attributes['training_course_id'] : $course->training_course_id,
            'qualification_id' => array_key_exists('qualification_id', $attributes) ? $attributes['qualification_id'] : $course->qualification_id,
            'asset_id' => array_key_exists('asset_id', $attributes) ? $attributes['asset_id'] : $course->asset_id,
            'competency_id' => array_key_exists('competency_id', $attributes) ? $attributes['competency_id'] : $course->competency_id,
            'competency_level' => array_key_exists('competency_level', $attributes) ? $attributes['competency_level'] : $course->competency_level,
            'article_id' => array_key_exists('article_id', $attributes) ? $attributes['article_id'] : $course->article_id,
            'owner_user_id' => array_key_exists('owner_user_id', $attributes) ? $attributes['owner_user_id'] : $course->owner_user_id,
            'duration_minutes' => array_key_exists('duration_minutes', $attributes) ? $attributes['duration_minutes'] : $course->duration_minutes,
            'validity_months' => array_key_exists('validity_months', $attributes) ? $attributes['validity_months'] : $course->validity_months,
            'points' => array_key_exists('points', $attributes) ? max(0, (int) $attributes['points']) : $course->points,
            'time_policy' => $attributes['time_policy'] ?? $course->time_policy,
            'instruction_suitability' => $attributes['instruction_suitability'] ?? $course->instruction_suitability,
            'certificate_enabled' => array_key_exists('certificate_enabled', $attributes) ? (bool) $attributes['certificate_enabled'] : $course->certificate_enabled,
            'creates_instruction_proof' => array_key_exists('creates_instruction_proof', $attributes) ? (bool) $attributes['creates_instruction_proof'] : $course->creates_instruction_proof,
            'access_days' => array_key_exists('access_days', $attributes) ? $attributes['access_days'] : $course->access_days,
            'sequential' => array_key_exists('sequential', $attributes) ? (bool) $attributes['sequential'] : $course->sequential,
        ]);

        $this->guardTimePolicy($course->refresh());

        return $course;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addSection(LearningCourse $course, array $attributes): LearningSection {
        $this->guardEditable($course);

        return $course->sections()->create([
            'organization_id' => $course->organization_id,
            'title' => (string) $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'position' => $attributes['position'] ?? ((int) $course->sections()->max('position') + 1),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addUnit(LearningCourse $course, array $attributes): LearningUnit {
        $this->guardEditable($course);

        $section = $attributes['section'] ?? null;
        if ($section instanceof LearningSection && $section->learning_course_id !== $course->id) {
            throw ValidationException::withMessages([
                'section' => (string) __('learning.errors.section_foreign'),
            ]);
        }

        $content = $attributes['content'] ?? null;

        return $course->units()->create([
            'organization_id' => $course->organization_id,
            'learning_section_id' => $section?->id,
            'title' => (string) $attributes['title'],
            'kind' => $attributes['kind'] ?? LearningUnitKind::Content->value,
            'position' => $attributes['position'] ?? ((int) $course->units()->max('position') + 1),
            'is_mandatory' => (bool) ($attributes['is_mandatory'] ?? true),
            'points' => max(0, (int) ($attributes['points'] ?? 0)),
            'duration_minutes' => $attributes['duration_minutes'] ?? null,
            'content' => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $content,
            'completion_rule' => $attributes['completion_rule'] ?? null,
            'release_rule' => $attributes['release_rule'] ?? null,
        ]);
    }

    /** Kurs zur Freigabe stellen (Vier-Augen: Autor ≠ Freigebender möglich). */
    public function submitForReview(LearningCourse $course): LearningCourse {
        if ($course->status !== LearningCourseStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.review_requires_draft'),
            ]);
        }

        $course->update(['status' => LearningCourseStatus::Review->value]);

        return $course->refresh();
    }

    /**
     * Freigabe: friert den Inhaltsbaum als Version ein und spiegelt sie in
     * den Trainingskurs aus Feature 145.
     */
    public function release(LearningCourse $course, ?User $actor, ?string $label = null): LearningCourseVersion {
        if ($course->status === LearningCourseStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.release_archived'),
            ]);
        }

        if ($course->units()->count() === 0) {
            throw ValidationException::withMessages([
                'units' => (string) __('learning.errors.release_without_units'),
            ]);
        }

        return DB::transaction(function () use ($course, $actor, $label): LearningCourseVersion {
            $next = (int) $course->versions()->max('version') + 1;

            $course->versions()->update(['is_current' => false]);

            $version = $course->versions()->create([
                'organization_id' => $course->organization_id,
                'version' => $next,
                'label' => $label,
                'content_snapshot' => json_encode($this->buildSnapshot($course), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'released_at' => now(),
                'released_by_user_id' => $actor?->id,
                'is_current' => true,
            ]);

            $course->update(['status' => LearningCourseStatus::Released->value]);

            $this->mirrorToTrainingCourse($course, $version, $label);

            return $version->refresh();
        });
    }

    /** Neue Bearbeitungsrunde: freigegebener Kurs geht zurück in den Entwurf. */
    public function reopen(LearningCourse $course): LearningCourse {
        if ($course->status !== LearningCourseStatus::Released) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.reopen_requires_released'),
            ]);
        }

        $course->update(['status' => LearningCourseStatus::Draft->value]);

        return $course->refresh();
    }

    public function archive(LearningCourse $course): LearningCourse {
        $course->update(['status' => LearningCourseStatus::Archived->value]);

        return $course->refresh();
    }

    /**
     * Inhaltsbaum für den Schnappschuss.
     *
     * @return array<string, mixed>
     */
    public function buildSnapshot(LearningCourse $course): array {
        $course->loadMissing(['sections', 'units']);

        return [
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
            'sections' => $course->sections->map(static fn (LearningSection $section): array => [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'position' => $section->position,
            ])->values()->all(),
            'units' => $course->units->map(static fn (LearningUnit $unit): array => [
                'id' => $unit->id,
                'section_id' => $unit->learning_section_id,
                'title' => $unit->title,
                'kind' => $unit->kind->value,
                'position' => $unit->position,
                'is_mandatory' => $unit->is_mandatory,
                'points' => $unit->points,
                'duration_minutes' => $unit->duration_minutes,
                'blocks' => $unit->blocks(),
                'completion_rule' => $unit->completion_rule,
                'release_rule' => $unit->release_rule,
            ])->values()->all(),
        ];
    }

    /**
     * Einzige Schreibrichtung zwischen 149 und 145: die LMS-Freigabe legt
     * die Trainings-Kursversion an, nie umgekehrt.
     */
    private function mirrorToTrainingCourse(LearningCourse $course, LearningCourseVersion $version, ?string $label): void {
        $trainingCourse = $course->trainingCourse;

        if ($trainingCourse === null) {
            return;
        }

        $trainingVersion = $this->trainingCatalog->addVersion($trainingCourse, [
            'label' => $label,
            'content_summary' => $course->objectives ?? $course->description,
        ]);

        $version->update(['training_course_version_id' => $trainingVersion->id]);
    }

    /** Freigegebener oder archivierter Inhalt ist gesperrt. */
    private function guardEditable(LearningCourse $course): void {
        if (! $course->isContentEditable()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.content_locked'),
            ]);
        }
    }

    /**
     * Ein Kurs mit Pflichtbezug darf nicht als „freiwillig unbezahlt"
     * geführt werden — sonst hülfe die Software, eine Pflicht in die
     * Freizeit zu verschieben (§ 12 Abs. 1 ArbSchG).
     */
    private function guardTimePolicy(LearningCourse $course): void {
        if ($course->time_policy === LearningTimePolicy::VoluntaryUnpaid && $course->training_course_id !== null) {
            throw ValidationException::withMessages([
                'time_policy' => (string) __('learning.errors.voluntary_policy_for_mandatory'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCode(Organization $organization, array $attributes): string {
        $base = Str::slug((string) ($attributes['code'] ?? $attributes['title'] ?? 'kurs'));
        $base = $base !== '' ? Str::limit($base, 55, '') : 'kurs';

        $code = $base;
        $suffix = 1;
        while (LearningCourse::query()->where('organization_id', $organization->id)->where('code', $code)->exists()) {
            $code = Str::limit($base, 50, '') . '-' . (++$suffix);
        }

        return $code;
    }

    /**
     * @return list<string>
     */
    private function normalizeAudiences(mixed $audiences): array {
        $values = is_array($audiences) ? $audiences : [$audiences];

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): ?string => LearningAudience::tryFrom((string) $value)?->value,
            $values
        ))));

        return $normalized !== [] ? $normalized : [LearningAudience::Internal->value];
    }
}
