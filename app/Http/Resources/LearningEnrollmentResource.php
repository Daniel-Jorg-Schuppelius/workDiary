<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningEnrollmentResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Learning\LearningProgressStatus;
use App\Models\Learning\{LearningUnit, LearningUnitProgress};
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Einschreibung in der API (Feature 149, MVP-791): Status, Fristen und —
 * wenn Kurs und Fortschritt geladen sind — der Stand je Einheit.
 *
 * @mixin \App\Models\Learning\LearningEnrollment
 */
class LearningEnrollmentResource extends JsonResource {
    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'learner' => $this->learnerName(),
            'course' => $this->whenLoaded('course', fn (): ?array => $this->course !== null ? [
                'id' => $this->course->sqid,
                'code' => $this->course->code,
                'title' => $this->course->title,
            ] : null),
            'due_at' => $this->due_at?->toDateString(),
            'access_until' => $this->access_until?->toDateString(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'points_earned' => (int) ($this->points_earned ?? 0),
            'progress_count' => $this->whenCounted('progress'),
            'units' => $this->when(
                $this->relationLoaded('progress') && $this->relationLoaded('course') && $this->course !== null && $this->course->relationLoaded('units'),
                function (): array {
                    $course = $this->course;
                    if ($course === null) {
                        return [];
                    }
                    $byUnit = $this->progress->keyBy('learning_unit_id');

                    return $course->units->map(static function (LearningUnit $unit) use ($byUnit): array {
                        /** @var LearningUnitProgress|null $progress */
                        $progress = $byUnit->get($unit->id);

                        return [
                            'id' => $unit->sqid,
                            'title' => $unit->title,
                            'kind' => $unit->kind->value,
                            'is_mandatory' => (bool) $unit->is_mandatory,
                            'status' => $progress?->status->value ?? LearningProgressStatus::Open->value,
                            'progress_percent' => (int) ($progress->progress_percent ?? 0),
                            'completed_at' => $progress?->completed_at?->toIso8601String(),
                        ];
                    })->all();
                },
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
