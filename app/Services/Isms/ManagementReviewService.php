<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementReviewService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\ReviewStatus;
use App\Models\Isms\{IsmsManagementReview, IsmsScope};
use App\Models\User;
use App\Services\Isms\Concerns\AssignsSequentialNo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aggregat-Service Managementbewertung (Feature 046, Inkrement C) — aus dem
 * AuditService herausgelöst (Refactoring Welle 2, B6b). Geschäftsregeln:
 * review_no laufend je Organisation; approve setzt approved_by/approved_at
 * (046-Prinzip „Freigabe mit Person/Zeitpunkt/Gegenstand"); danach sind
 * update/approve/delete gesperrt (ValidationException).
 */
class ManagementReviewService {
    use AssignsSequentialNo;

    /**
     * Legt eine Managementbewertung an (Status immer draft).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createReview(User $creator, IsmsScope $scope, array $attributes): IsmsManagementReview {
        return DB::transaction(function () use ($creator, $scope, $attributes): IsmsManagementReview {
            return IsmsManagementReview::query()->create([
                'organization_id' => $creator->organization_id,
                'isms_scope_id' => $scope->id,
                'review_no' => $this->nextNo(IsmsManagementReview::class, 'review_no', 'organization_id', (int) $creator->organization_id),
                'held_on' => $attributes['held_on'],
                'participants' => $attributes['participants'],
                'inputs' => $attributes['inputs'],
                'decisions' => $attributes['decisions'],
                'follow_ups' => $attributes['follow_ups'] ?? null,
                'status' => ReviewStatus::Draft->value,
            ]);
        });
    }

    /**
     * Aktualisiert eine Managementbewertung — NUR im Entwurf; freigegebene
     * Bewertungen sind unveränderlich (046-Prinzip).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException bei bereits freigegebener Bewertung
     */
    public function updateReview(IsmsManagementReview $review, User $actor, array $attributes): IsmsManagementReview {
        $this->assertReviewMutable($review);

        return DB::transaction(function () use ($review, $actor, $attributes): IsmsManagementReview {
            unset($actor);

            $review->update([
                'held_on' => $attributes['held_on'] ?? $review->held_on,
                'participants' => $attributes['participants'] ?? $review->participants,
                'inputs' => $attributes['inputs'] ?? $review->inputs,
                'decisions' => $attributes['decisions'] ?? $review->decisions,
                'follow_ups' => array_key_exists('follow_ups', $attributes) ? $attributes['follow_ups'] : $review->follow_ups,
            ]);

            return $review;
        });
    }

    /**
     * Freigabe (draft → approved): setzt Person + Zeitpunkt
     * (046-Prinzip „Freigabe mit Person/Zeitpunkt/Gegenstand");
     * danach ist die Bewertung NICHT mehr editierbar.
     *
     * @throws ValidationException bei bereits freigegebener Bewertung
     */
    public function approveReview(IsmsManagementReview $review, User $actor): IsmsManagementReview {
        $this->assertReviewMutable($review);

        return DB::transaction(function () use ($review, $actor): IsmsManagementReview {
            $review->update([
                'status' => ReviewStatus::Approved->value,
                'approved_by_user_id' => $actor->id,
                'approved_at' => Carbon::now(),
            ]);

            $review->audit('isms.management_review.approved', ['actor_user_id' => $actor->id]);

            return $review;
        });
    }

    /**
     * Soft-Delete — NUR im Entwurf; freigegebene Bewertungen bleiben als
     * Nachweis erhalten (Historisierung statt Löschung).
     *
     * @throws ValidationException bei bereits freigegebener Bewertung
     */
    public function deleteReview(IsmsManagementReview $review, User $actor): void {
        $this->assertReviewMutable($review);

        DB::transaction(function () use ($review, $actor): void {
            $review->audit('isms.management_review.deleted', ['actor_user_id' => $actor->id]);
            $review->delete();
        });
    }

    /** @throws ValidationException wenn die Bewertung bereits freigegeben ist */
    private function assertReviewMutable(IsmsManagementReview $review): void {
        if ($review->isApproved()) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.review_already_approved'),
            ]);
        }
    }
}
