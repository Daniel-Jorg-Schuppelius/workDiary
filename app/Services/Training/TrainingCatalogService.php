<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingCatalogService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\{Organization, User};
use App\Models\Training\{TrainingAssignment, TrainingCourse, TrainingCourseVersion};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Schulungskatalog (Feature 145): Kurse und ihre Versionen. Kurse werden
 * ausgemustert (`is_active`), nicht gelöscht, sobald ein Nachweis daran
 * hängt — der Nachweis selbst liegt im Arbeitsschutz-Register (132) und
 * darf seinen Kursbezug nicht verlieren.
 */
class TrainingCatalogService {
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCourse(Organization $organization, ?User $actor, array $attributes): TrainingCourse {
        return DB::transaction(function () use ($organization, $actor, $attributes): TrainingCourse {
            $course = TrainingCourse::query()->create([
                'organization_id' => $organization->id,
                'code' => $this->resolveCode($organization, $attributes),
                'title' => (string) $attributes['title'],
                'provider_kind' => $attributes['provider_kind'] ?? 'internal',
                'provider_name' => $attributes['provider_name'] ?? null,
                'duration_minutes' => $attributes['duration_minutes'] ?? null,
                'validity_months' => $attributes['validity_months'] ?? null,
                'is_mandatory' => (bool) ($attributes['is_mandatory'] ?? false),
                'legal_basis' => $attributes['legal_basis'] ?? null,
                'cost_amount' => $attributes['cost_amount'] ?? null,
                'cost_currency' => $attributes['cost_currency'] ?? null,
                'lead_days' => max(0, (int) ($attributes['lead_days'] ?? 30)),
                'notes' => $attributes['notes'] ?? null,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'source' => (string) ($attributes['source'] ?? 'manual'),
                'created_by_user_id' => $actor?->id,
            ]);

            // Jeder Kurs startet mit v1 — der Nachweis soll immer eine
            // Kursversion nennen können (ISO 45001 7.2).
            $this->addVersion($course, ['label' => $attributes['version_label'] ?? null]);

            return $course->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCourse(TrainingCourse $course, array $attributes): TrainingCourse {
        $previousLead = $course->leadDays();

        $course->update([
            'title' => $attributes['title'] ?? $course->title,
            'provider_kind' => $attributes['provider_kind'] ?? $course->provider_kind,
            'provider_name' => array_key_exists('provider_name', $attributes) ? $attributes['provider_name'] : $course->provider_name,
            'duration_minutes' => array_key_exists('duration_minutes', $attributes) ? $attributes['duration_minutes'] : $course->duration_minutes,
            'validity_months' => array_key_exists('validity_months', $attributes) ? $attributes['validity_months'] : $course->validity_months,
            'is_mandatory' => array_key_exists('is_mandatory', $attributes) ? (bool) $attributes['is_mandatory'] : $course->is_mandatory,
            'legal_basis' => array_key_exists('legal_basis', $attributes) ? $attributes['legal_basis'] : $course->legal_basis,
            'cost_amount' => array_key_exists('cost_amount', $attributes) ? $attributes['cost_amount'] : $course->cost_amount,
            'cost_currency' => array_key_exists('cost_currency', $attributes) ? $attributes['cost_currency'] : $course->cost_currency,
            'lead_days' => array_key_exists('lead_days', $attributes) ? max(0, (int) $attributes['lead_days']) : $course->lead_days,
            'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $course->notes,
            'is_active' => array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : $course->is_active,
        ]);
        $course->refresh();

        // Geänderter Vorlauf verschiebt das Meldefenster aller offenen Soll-
        // Einträge — sonst meldete der Scan weiter nach der alten Regel.
        if ($course->leadDays() !== $previousLead) {
            $this->recomputeNotifyFrom($course);
        }

        return $course;
    }

    /** Kurse mit Nachweis bleiben stehen (ausmustern statt löschen). */
    public function deleteCourse(TrainingCourse $course): void {
        $hasProof = TrainingAssignment::query()
            ->where('training_course_id', $course->id)
            ->whereNotNull('fulfilled_at')
            ->exists();
        if ($hasProof) {
            throw ValidationException::withMessages([
                'course' => (string) __('training.error.delete_with_proof'),
            ]);
        }

        DB::transaction(function () use ($course): void {
            $course->assignments()->delete();
            $course->requirements()->delete();
            $course->versions()->delete();
            $course->delete();
        });
    }

    /**
     * Neue Kursversion: laufende Nummer je Kurs, wird automatisch die
     * aktuelle (genau eine `is_current` je Kurs).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addVersion(TrainingCourse $course, array $attributes = []): TrainingCourseVersion {
        return DB::transaction(function () use ($course, $attributes): TrainingCourseVersion {
            $next = (int) $course->versions()->max('version') + 1;

            $course->versions()->update(['is_current' => false]);
            $version = $course->versions()->create([
                'organization_id' => $course->organization_id,
                'version' => $next,
                'label' => $attributes['label'] ?? null,
                'valid_from' => $attributes['valid_from'] ?? null,
                'content_summary' => $attributes['content_summary'] ?? null,
                'is_current' => true,
            ]);

            return $version;
        });
    }

    /** Version löschen — nie die letzte, nie eine mit Nachweis-Bezug. */
    public function deleteVersion(TrainingCourseVersion $version): void {
        $course = $version->course()->firstOrFail();
        if ($course->versions()->count() <= 1) {
            throw ValidationException::withMessages([
                'version' => (string) __('training.error.delete_last_version'),
            ]);
        }

        $referenced = \App\Models\Safety\SafetyInstruction::query()
            ->where('training_course_version_id', $version->id)
            ->exists();
        if ($referenced) {
            throw ValidationException::withMessages([
                'version' => (string) __('training.error.delete_version_in_use'),
            ]);
        }

        DB::transaction(function () use ($course, $version): void {
            $wasCurrent = $version->is_current;
            $version->delete();

            if ($wasCurrent) {
                $latest = $course->versions()->orderByDesc('version')->first();
                $latest?->update(['is_current' => true]);
            }
        });
    }

    /** Meldefenster (`notify_from`) aller offenen Soll-Einträge nachziehen. */
    public function recomputeNotifyFrom(TrainingCourse $course): void {
        $lead = $course->leadDays();

        TrainingAssignment::query()
            ->where('training_course_id', $course->id)
            ->whereNotNull('due_at')
            ->get()
            ->each(function (TrainingAssignment $assignment) use ($lead): void {
                $assignment->update([
                    'notify_from' => $assignment->due_at?->copy()->subDays($lead)->toDateString(),
                ]);
            });
    }

    /**
     * Kurscode: übergebener Wert oder aus dem Titel abgeleitet, je Org
     * eindeutig (Anker für idempotente Profil-Vorschläge).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCode(Organization $organization, array $attributes): string {
        $code = trim((string) ($attributes['code'] ?? ''));
        if ($code === '') {
            $code = Str::limit(StringHelper::slugify((string) ($attributes['title'] ?? 'kurs')), 50, '');
        }
        $code = $code === '' ? 'kurs' : $code;

        $candidate = $code;
        $suffix = 1;
        while (TrainingCourse::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('code', $candidate)
            ->exists()) {
            $suffix++;
            $candidate = Str::limit($code, 55, '') . '-' . $suffix;
        }

        return $candidate;
    }
}
