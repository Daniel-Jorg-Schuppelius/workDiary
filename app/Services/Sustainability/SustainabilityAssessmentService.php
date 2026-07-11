<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityAssessmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sustainability;

use App\Models\Sustainability\{SustainabilityAssessment, SustainabilityCriterion};
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ESG-Bewertungen (Feature 071, MVP-224–226): Entwurf mit Items aus dem
 * aktiven Kriterienkatalog, Finalisierung friert Score/Gewichte/Kontext
 * als Snapshot ein; Änderungen erzeugen neue Versionen (P2).
 */
class SustainabilityAssessmentService {
    public function __construct(private readonly EmissionCalculationService $emissions) {}

    public function createDraft(int $organizationId, ?string $subjectType, ?int $subjectId, string $subjectLabel, User $actor): SustainabilityAssessment {
        $criteria = SustainabilityCriterion::query()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->orderBy('dimension')
            ->get();
        if ($criteria->isEmpty()) {
            throw new \RuntimeException((string) __('Ohne aktive Kriterien keine Bewertung — zuerst den Kriterienkatalog pflegen.'));
        }

        return DB::transaction(function () use ($organizationId, $subjectType, $subjectId, $subjectLabel, $actor, $criteria): SustainabilityAssessment {
            $version = 1;
            if ($subjectType !== null && $subjectId !== null) {
                $version = (int) SustainabilityAssessment::query()
                    ->where('organization_id', $organizationId)
                    ->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId)
                    ->max('version') + 1;
            }

            $assessment = SustainabilityAssessment::query()->create([
                'organization_id' => $organizationId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_label' => $subjectLabel,
                'version' => $version,
                'status' => 'draft',
                'assessed_by' => $actor->id,
            ]);

            foreach ($criteria as $criterion) {
                $assessment->items()->create([
                    'organization_id' => $organizationId,
                    'criterion_id' => $criterion->id,
                    'weight' => (int) $criterion->weight, // Gewicht-Snapshot
                    'data_quality' => 'estimated',
                ]);
            }

            $assessment->audit('sustainability.assessment_drafted', ['subject' => $subjectLabel, 'version' => $version]);

            return $assessment;
        });
    }

    /**
     * Finalisierung: gewichteter Score (0–5), Ampel, schwächste
     * Datenqualität, vollständiger Item-/Methodik-Snapshot.
     */
    public function finalize(SustainabilityAssessment $assessment, User $actor): SustainabilityAssessment {
        if ($assessment->isFinal()) {
            throw new \RuntimeException((string) __('Die Bewertung ist bereits final — Änderungen laufen über eine neue Version.'));
        }

        $items = $assessment->items()->with('criterion')->get();
        $scored = $items->filter(fn($item): bool => $item->score !== null);
        if ($scored->isEmpty()) {
            throw new \RuntimeException((string) __('Mindestens ein Kriterium muss bewertet sein.'));
        }

        $weightSum = (int) $scored->sum('weight');
        $score = $weightSum > 0
            ? round((float) $scored->sum(fn($item): float => (float) $item->score * (int) $item->weight) / $weightSum, 2)
            : 0.0;
        $rating = $score >= 3.5 ? 'green' : ($score >= 2.0 ? 'yellow' : 'red');
        $qualityRank = ['measured' => 3, 'calculated' => 2, 'estimated' => 1];
        $worst = $scored->sortBy(fn($item): int => $qualityRank[$item->data_quality] ?? 1)->first();

        $assessment->update([
            'status' => 'final',
            'total_score' => (string) $score,
            'rating' => $rating,
            'data_quality' => $worst?->data_quality,
            'assessed_by' => $actor->id,
            'assessed_at' => now(),
            'snapshot' => [
                'items' => $items->map(fn($item): array => [
                    'criterion' => $item->criterion?->label,
                    'dimension' => $item->criterion?->dimension,
                    'score' => $item->score,
                    'weight' => $item->weight,
                    'data_quality' => $item->data_quality,
                    'source_note' => $item->source_note,
                    'justification' => $item->justification,
                ])->all(),
                'methodology' => [
                    'factor_sets' => $this->emissions->activeSetNames((int) $assessment->organization_id),
                    'scoring' => 'gewichteter Mittelwert 0–5; Ampel: ≥3,5 grün, ≥2,0 gelb, sonst rot',
                ],
                'finalized_at' => now()->toIso8601String(),
            ],
        ]);
        $assessment->audit('sustainability.assessment_finalized', ['score' => $score, 'rating' => $rating]);

        return $assessment->refresh();
    }

    /** Neue Version aus einer finalen Bewertung (Kriterien/Scores kopiert). */
    public function newVersion(SustainabilityAssessment $assessment, User $actor): SustainabilityAssessment {
        if (! $assessment->isFinal()) {
            throw new \RuntimeException((string) __('Nur finale Bewertungen werden versioniert.'));
        }

        return DB::transaction(function () use ($assessment, $actor): SustainabilityAssessment {
            $next = $assessment->replicate(['status', 'total_score', 'rating', 'data_quality', 'snapshot', 'assessed_at']);
            $next->version = $assessment->version + 1;
            $next->status = 'draft';
            $next->assessed_by = $actor->id;
            $next->save();

            foreach ($assessment->items as $item) {
                $copy = $item->replicate();
                $copy->assessment_id = $next->id;
                $copy->save();
            }

            $next->audit('sustainability.assessment_versioned', ['from' => $assessment->version]);

            return $next->refresh();
        });
    }
}
