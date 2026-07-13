<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{AuditStatus, CorrectiveActionStatus, FindingStatus};
use App\Models\Isms\{IsmsAudit, IsmsAuditFinding, IsmsCorrectiveAction, IsmsManagementReview, IsmsScope};
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Fassade über die vier Aggregat-Services für Audits, Feststellungen,
 * Korrekturmaßnahmen und Managementbewertung (Feature 046, Inkrement C —
 * Muster ConformityService). Die Geschäftsregeln liegen seit der
 * God-Klassen-Aufteilung (Refactoring Welle 2, B6b) in
 * {@see AuditRecordService}, {@see FindingService},
 * {@see CorrectiveActionService} und {@see ManagementReviewService};
 * der öffentliche Vertrag dieser Fassade ist unverändert.
 *
 * Geschäftsregeln (in den Aggregat-Services zentral erzwungen):
 * - audit_no/review_no: laufende Nummer je Organisation (Vergabe in der
 *   Transaktion, Muster risk_no); finding_no: laufend je Audit.
 * - Audit-Übergänge entlang AuditStatus::allowedTransitions();
 *   reportIssued NUR mit performed_from/to + summary.
 * - Feststellungen sind nur bei Audit-Status inProgress/reportIssued
 *   anlegbar.
 * - Feststellungs-Abschluss (closed) NUR, wenn alle Korrekturmaßnahmen
 *   done/effective sind; Nichtkonformitäten (major/minor) brauchen
 *   zusätzlich mindestens EINE wirksame (effective) Maßnahme.
 * - Wirksamkeitsprüfung (effective/ineffective) NUR mit Pflicht-Notiz;
 *   ineffective setzt die Feststellung zurück auf inCorrection.
 * - Managementbewertung: approve setzt approved_by/approved_at
 *   (046-Prinzip „Freigabe mit Person/Zeitpunkt/Gegenstand"); danach sind
 *   update/approve/delete gesperrt (ValidationException).
 *
 * FK-Auflösung (requirement, owner, lead auditor) erfolgt org-sicher: die
 * org-gescopte Requirement-Query sieht fremde Anforderungen nicht; User
 * werden explizit gegen die organization_id geprüft.
 */
class AuditService {
    public function __construct(
        private readonly AuditRecordService $audits,
        private readonly FindingService $findings,
        private readonly CorrectiveActionService $actions,
        private readonly ManagementReviewService $reviews,
    ) {}

    // ── Audits ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function createAudit(User $creator, IsmsScope $scope, array $attributes): IsmsAudit {
        return $this->audits->createAudit($creator, $scope, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function updateAudit(IsmsAudit $audit, User $actor, array $attributes): IsmsAudit {
        return $this->audits->updateAudit($audit, $actor, $attributes);
    }

    /** @throws ValidationException */
    public function transitionAudit(IsmsAudit $audit, AuditStatus $target, User $actor): IsmsAudit {
        return $this->audits->transitionAudit($audit, $target, $actor);
    }

    public function deleteAudit(IsmsAudit $audit, User $actor): void {
        $this->audits->deleteAudit($audit, $actor);
    }

    // ── Feststellungen ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function createFinding(IsmsAudit $audit, User $actor, array $attributes): IsmsAuditFinding {
        return $this->findings->createFinding($audit, $actor, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function updateFinding(IsmsAuditFinding $finding, User $actor, array $attributes): IsmsAuditFinding {
        return $this->findings->updateFinding($finding, $actor, $attributes);
    }

    /** @throws ValidationException */
    public function transitionFinding(IsmsAuditFinding $finding, FindingStatus $target, User $actor): IsmsAuditFinding {
        return $this->findings->transitionFinding($finding, $target, $actor);
    }

    public function deleteFinding(IsmsAuditFinding $finding, User $actor): void {
        $this->findings->deleteFinding($finding, $actor);
    }

    // ── Korrekturmaßnahmen ─────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function createAction(IsmsAuditFinding $finding, User $actor, array $attributes): IsmsCorrectiveAction {
        return $this->actions->createAction($finding, $actor, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function updateAction(IsmsCorrectiveAction $action, User $actor, array $attributes): IsmsCorrectiveAction {
        return $this->actions->updateAction($action, $actor, $attributes);
    }

    /** @throws ValidationException */
    public function transitionAction(
        IsmsCorrectiveAction $action,
        CorrectiveActionStatus $target,
        User $actor,
        ?string $effectivenessNote = null,
    ): IsmsCorrectiveAction {
        return $this->actions->transitionAction($action, $target, $actor, $effectivenessNote);
    }

    public function deleteAction(IsmsCorrectiveAction $action, User $actor): void {
        $this->actions->deleteAction($action, $actor);
    }

    // ── Managementbewertung ────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function createReview(User $creator, IsmsScope $scope, array $attributes): IsmsManagementReview {
        return $this->reviews->createReview($creator, $scope, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException bei bereits freigegebener Bewertung
     */
    public function updateReview(IsmsManagementReview $review, User $actor, array $attributes): IsmsManagementReview {
        return $this->reviews->updateReview($review, $actor, $attributes);
    }

    /** @throws ValidationException bei bereits freigegebener Bewertung */
    public function approveReview(IsmsManagementReview $review, User $actor): IsmsManagementReview {
        return $this->reviews->approveReview($review, $actor);
    }

    /** @throws ValidationException bei bereits freigegebener Bewertung */
    public function deleteReview(IsmsManagementReview $review, User $actor): void {
        $this->reviews->deleteReview($review, $actor);
    }
}
