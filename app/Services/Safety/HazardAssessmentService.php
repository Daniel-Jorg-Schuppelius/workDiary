<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardAssessmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Safety;

use App\Enums\Safety\HazardAssessmentStatus;
use App\Models\{Organization, User};
use App\Models\Safety\{HazardAssessment, HazardAssessmentItem};
use App\Services\Concerns\AssignsSequentialNo;
use App\Services\Isms\Concerns\AssertsIsmsTransition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service der Gefährdungsbeurteilung (§ 5 ArbSchG, Feature 132):
 * Anlage (laufende assessment_no je Org), Positionen mit Risiko = Schwere ×
 * Wahrscheinlichkeit, Statusmaschine (HazardAssessmentStatus) und die
 * Versionskette nach Protokoll-Muster — approve friert ein, newVersion()
 * legt die Folgeversion an und archiviert das Original.
 */
class HazardAssessmentService {
    use AssertsIsmsTransition;
    use AssignsSequentialNo;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Organization $organization, User $creator, array $attributes): HazardAssessment {
        return DB::transaction(function () use ($organization, $creator, $attributes): HazardAssessment {
            return HazardAssessment::query()->create([
                'organization_id' => $organization->id,
                'assessment_no' => $this->nextNo(HazardAssessment::class, 'assessment_no', 'organization_id', (int) $organization->id),
                'version' => 1,
                'area' => $attributes['area'],
                'activity' => $attributes['activity'] ?? null,
                'description' => $attributes['description'] ?? null,
                'status' => HazardAssessmentStatus::Draft->value,
                'review_due_on' => $attributes['review_due_on'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(HazardAssessment $assessment, array $attributes): HazardAssessment {
        $this->assertEditable($assessment);

        $assessment->update([
            'area' => $attributes['area'] ?? $assessment->area,
            'activity' => array_key_exists('activity', $attributes) ? $attributes['activity'] : $assessment->activity,
            'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $assessment->description,
            'review_due_on' => array_key_exists('review_due_on', $attributes) ? $attributes['review_due_on'] : $assessment->review_due_on,
        ]);

        return $assessment->refresh();
    }

    /**
     * Statusübergang gemäß HazardAssessmentStatus::allowedTransitions().
     * Die Freigabe verlangt mindestens eine Gefährdungs-Position und setzt
     * Person + Zeitpunkt; ab dann greift der Einfrier-Guard des Modells.
     */
    public function transition(HazardAssessment $assessment, HazardAssessmentStatus $target, User $actor): HazardAssessment {
        if ($assessment->status === $target) {
            return $assessment;
        }

        $this->assertIsmsTransition($assessment->status, $target, 'safety.error.invalid_transition');

        $changes = ['status' => $target->value];

        if ($target === HazardAssessmentStatus::Approved) {
            if (! $assessment->items()->exists()) {
                throw ValidationException::withMessages([
                    'status' => (string) __('safety.register.error.approve_requires_items'),
                ]);
            }
            $changes['approved_by_user_id'] = $actor->id;
            $changes['approved_at'] = Carbon::now();
        }

        DB::transaction(function () use ($assessment, $changes, $actor, $target): void {
            $assessment->update($changes);
            $assessment->audit(
                $target === HazardAssessmentStatus::Approved ? 'safety.hazard_assessment.approved' : 'safety.hazard_assessment.transitioned',
                ['actor_user_id' => $actor->id, 'status' => $target->value],
            );
        });

        return $assessment->refresh();
    }

    /**
     * Folgeversion eines freigegebenen Standes: Kopf + Positionen werden in
     * einen neuen Entwurf (version + 1, supersedes_id) kopiert, das Original
     * wird archiviert. Nur aus dem Status approved heraus.
     */
    public function newVersion(HazardAssessment $assessment, User $actor): HazardAssessment {
        if ($assessment->status !== HazardAssessmentStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => (string) __('safety.register.error.new_version_requires_approved'),
            ]);
        }

        return DB::transaction(function () use ($assessment, $actor): HazardAssessment {
            $copy = HazardAssessment::query()->create([
                'organization_id' => $assessment->organization_id,
                'assessment_no' => $assessment->assessment_no,
                'version' => $assessment->version + 1,
                'supersedes_id' => $assessment->id,
                'area' => $assessment->area,
                'activity' => $assessment->activity,
                'description' => $assessment->description,
                'status' => HazardAssessmentStatus::Draft->value,
                'review_due_on' => $assessment->review_due_on,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($assessment->items as $item) {
                $copy->items()->create([
                    'organization_id' => $assessment->organization_id,
                    'position' => $item->position,
                    'hazard' => $item->hazard,
                    'measure' => $item->measure,
                    'severity_before' => $item->severity_before,
                    'likelihood_before' => $item->likelihood_before,
                    'risk_before' => $item->risk_before,
                    'severity_after' => $item->severity_after,
                    'likelihood_after' => $item->likelihood_after,
                    'risk_after' => $item->risk_after,
                ]);
            }

            $assessment->update(['status' => HazardAssessmentStatus::Archived->value]);
            $assessment->audit('safety.hazard_assessment.superseded', [
                'actor_user_id' => $actor->id,
                'replacement_id' => $copy->id,
            ]);

            return $copy->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addItem(HazardAssessment $assessment, array $attributes): HazardAssessmentItem {
        $this->assertEditable($assessment);

        $position = ((int) $assessment->items()->max('position')) + 1;

        return $assessment->items()->create([
            'organization_id' => $assessment->organization_id,
            'position' => $position,
        ] + $this->itemAttributes($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateItem(HazardAssessmentItem $item, array $attributes): HazardAssessmentItem {
        $this->assertEditable($item->assessment()->firstOrFail());

        $item->update($this->itemAttributes($attributes));

        return $item->refresh();
    }

    public function removeItem(HazardAssessmentItem $item): void {
        $this->assertEditable($item->assessment()->firstOrFail());
        $item->delete();
    }

    /** Nur Entwürfe/in Prüfung sind löschbar (der Model-Guard wirft zusätzlich). */
    public function delete(HazardAssessment $assessment): void {
        $this->assertEditable($assessment);
        $assessment->delete();
    }

    /**
     * Risiko vor/nach Maßnahme = Schwere × Wahrscheinlichkeit; das Nach-Paar
     * ist optional, aber nur gemeinsam gültig.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function itemAttributes(array $attributes): array {
        $severityBefore = (int) $attributes['severity_before'];
        $likelihoodBefore = (int) $attributes['likelihood_before'];
        $severityAfter = isset($attributes['severity_after']) && $attributes['severity_after'] !== '' ? (int) $attributes['severity_after'] : null;
        $likelihoodAfter = isset($attributes['likelihood_after']) && $attributes['likelihood_after'] !== '' ? (int) $attributes['likelihood_after'] : null;

        if (($severityAfter === null) !== ($likelihoodAfter === null)) {
            throw ValidationException::withMessages([
                'severity_after' => (string) __('safety.register.error.after_pair_incomplete'),
            ]);
        }

        return [
            'hazard' => $attributes['hazard'],
            'measure' => $attributes['measure'] ?? null,
            'severity_before' => $severityBefore,
            'likelihood_before' => $likelihoodBefore,
            'risk_before' => $severityBefore * $likelihoodBefore,
            'severity_after' => $severityAfter,
            'likelihood_after' => $likelihoodAfter,
            'risk_after' => $severityAfter !== null && $likelihoodAfter !== null ? $severityAfter * $likelihoodAfter : null,
        ];
    }

    private function assertEditable(HazardAssessment $assessment): void {
        if (! $assessment->isEditable()) {
            throw ValidationException::withMessages([
                'status' => (string) __('safety.register.error.assessment_frozen'),
            ]);
        }
    }
}
