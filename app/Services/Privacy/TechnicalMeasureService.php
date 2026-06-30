<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TechnicalMeasureService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\Privacy\{MeasureCategory, ReviewResult};
use App\Models\Organization;
use App\Models\Privacy\{MeasureAssignment, MeasureReview, ProcessingActivity, ProcessingAgreement, TechnicalMeasure, TechnicalMeasureVersion};
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TOM-Katalog (Art. 32): Anlage und Versionierung, Zuordnung zu VVT/AVV und
 * dokumentierte Wirksamkeitspruefung. Liefert ausserdem den TOM-Snapshot fuer
 * freigegebene VVT-/Vertragsversionen.
 */
class TechnicalMeasureService {
    /** @param array<string, mixed> $payload */
    public function createDraft(Organization $organization, string $name, MeasureCategory $category, array $payload, ?User $actor = null): TechnicalMeasure {
        return DB::transaction(function () use ($organization, $name, $category, $payload, $actor): TechnicalMeasure {
            $measure = TechnicalMeasure::create([
                'organization_id' => $organization->id,
                'name' => $name,
                'category' => $category,
                'created_by' => $actor?->id,
            ]);
            $this->addVersion($measure, $payload, $actor, 'Erstentwurf');

            return $measure;
        });
    }

    /** @param array<string, mixed> $payload */
    public function addVersion(TechnicalMeasure $measure, array $payload, ?User $actor = null, ?string $note = null): TechnicalMeasureVersion {
        $next = (int) $measure->versions()->max('version_no') + 1;

        return TechnicalMeasureVersion::create([
            'organization_id' => $measure->organization_id,
            'measure_id' => $measure->id,
            'version_no' => $next,
            'payload' => $payload,
            'note' => $note,
            'created_by' => $actor?->id,
        ]);
    }

    public function approve(TechnicalMeasure $measure, TechnicalMeasureVersion $version, User $approver): TechnicalMeasure {
        return DB::transaction(function () use ($measure, $version, $approver): TechnicalMeasure {
            $now = Carbon::now();
            $version->forceFill([
                'approved_by' => $approver->id,
                'approved_at' => $now,
                'valid_from' => $now->toDateString(),
            ])->save();
            $measure->forceFill(['current_version_id' => $version->id])->save();

            return $measure;
        });
    }

    public function assignToActivity(TechnicalMeasure $measure, ProcessingActivity $activity): MeasureAssignment {
        return MeasureAssignment::firstOrCreate([
            'organization_id' => $measure->organization_id,
            'measure_id' => $measure->id,
            'activity_id' => $activity->id,
            'agreement_id' => null,
        ]);
    }

    public function assignToAgreement(TechnicalMeasure $measure, ProcessingAgreement $agreement): MeasureAssignment {
        return MeasureAssignment::firstOrCreate([
            'organization_id' => $measure->organization_id,
            'measure_id' => $measure->id,
            'activity_id' => null,
            'agreement_id' => $agreement->id,
        ]);
    }

    public function recordReview(TechnicalMeasure $measure, ReviewResult $result, ?string $deviation, ?string $followUp, ?Carbon $dueAt, ?User $reviewer = null): MeasureReview {
        $review = MeasureReview::create([
            'organization_id' => $measure->organization_id,
            'measure_id' => $measure->id,
            'reviewed_at' => Carbon::now(),
            'result' => $result,
            'deviation' => $deviation,
            'follow_up' => $followUp,
            'due_at' => $dueAt?->toDateString(),
            'reviewer_id' => $reviewer?->id,
        ]);
        // Naechsten Wirksamkeitsreview terminieren (Fälligkeit der Folgemassnahme bzw. +1 Jahr).
        $measure->forceFill([
            'next_review_at' => ($dueAt ?? Carbon::now()->addYear())->toDateString(),
        ])->save();

        return $review;
    }

    /**
     * Unveraenderlicher TOM-Snapshot der einer Verarbeitungstaetigkeit
     * zugeordneten Massnahmen (Name, Kategorie, Status, aktuelle Version).
     *
     * @return list<array<string, mixed>>
     */
    public function snapshotForActivity(ProcessingActivity $activity): array {
        $measures = TechnicalMeasure::query()
            ->whereIn('id', MeasureAssignment::query()
                ->where('activity_id', $activity->id)
                ->pluck('measure_id'))
            ->with('currentVersion')
            ->get();

        $snapshot = [];
        foreach ($measures as $m) {
            $snapshot[] = [
                'name' => $m->name,
                'category' => $m->category->value,
                'implementation_status' => $m->implementation_status->value,
                'version_no' => $m->currentVersion?->version_no,
                'payload' => $m->currentVersion?->payload,
            ];
        }

        return $snapshot;
    }
}
