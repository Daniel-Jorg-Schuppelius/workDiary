<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Learning\LearningUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lernkurs in der API (Feature 149, MVP-791): Stammdaten und — wenn
 * geladen — die Einheiten ohne Inhalt (der Stoff gehört in den Player,
 * Prüfungsfragen nie in eine API-Antwort).
 *
 * @mixin \App\Models\Learning\LearningCourse
 */
class LearningCourseResource extends JsonResource {
    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'code' => $this->code,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'objectives' => $this->objectives,
            'language' => $this->language,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'access_kind' => $this->access_kind->value,
            'audiences' => $this->audiences ?? [],
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category !== null ? ['id' => $this->category->sqid, 'name' => $this->category->name] : null),
            'duration_minutes' => $this->duration_minutes,
            'validity_months' => $this->validity_months,
            'points' => (int) $this->points,
            'certificate_enabled' => (bool) $this->certificate_enabled,
            'available_from' => $this->available_from?->toDateString(),
            'available_until' => $this->available_until?->toDateString(),
            'seat_limit' => $this->max_enrollments,
            'units_count' => $this->whenCounted('units'),
            'units' => $this->whenLoaded('units', fn (): array => $this->units->map(static fn (LearningUnit $unit): array => [
                'id' => $unit->sqid,
                'title' => $unit->title,
                'kind' => $unit->kind->value,
                'section' => $unit->section->title ?? null,
                'position' => (int) $unit->position,
                'is_mandatory' => (bool) $unit->is_mandatory,
                'is_preview' => (bool) $unit->is_preview,
                'points' => (int) $unit->points,
                'duration_minutes' => $unit->duration_minutes,
            ])->all()),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
