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

use App\Enums\Isms\{AuditStatus, CorrectiveActionStatus, FindingKind, FindingStatus, ReviewStatus};
use App\Models\Isms\{IsmsAudit, IsmsAuditFinding, IsmsCorrectiveAction, IsmsManagementReview, IsmsRequirement, IsmsScope};
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service Audits, Feststellungen, Korrekturmaßnahmen und
 * Managementbewertung (Feature 046, Inkrement C — Muster ConformityService).
 *
 * Geschäftsregeln (alle hier zentral erzwungen):
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
    // ── Audits ─────────────────────────────────────────────────────────────

    /**
     * Legt ein Audit an (Status immer planned — Statuskette nur über
     * transitionAudit()).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createAudit(User $creator, IsmsScope $scope, array $attributes): IsmsAudit {
        return DB::transaction(function () use ($creator, $scope, $attributes): IsmsAudit {
            return IsmsAudit::query()->create([
                'organization_id' => $creator->organization_id,
                'isms_scope_id' => $scope->id,
                'audit_no' => $this->nextNo(IsmsAudit::class, 'audit_no', (int) $creator->organization_id),
                'title' => $attributes['title'],
                'norm' => $attributes['norm'] ?? null,
                'edition' => $attributes['edition'] ?? null,
                'kind' => $attributes['kind'],
                'status' => AuditStatus::Planned->value,
                'planned_on' => $attributes['planned_on'] ?? null,
                'isms_audit_program_id' => $attributes['isms_audit_program_id'] ?? null,
                'performed_from' => $attributes['performed_from'] ?? null,
                'performed_to' => $attributes['performed_to'] ?? null,
                'lead_auditor_user_id' => $this->resolveUserId($attributes['lead_auditor_user_id'] ?? null, (int) $creator->organization_id, 'lead_auditor_user_id'),
                'auditors' => $attributes['auditors'] ?? null,
                'criteria' => $attributes['criteria'] ?? null,
                'independence_note' => $attributes['independence_note'] ?? null,
                'summary' => $attributes['summary'] ?? null,
            ]);
        });
    }

    /**
     * Aktualisiert Stammdaten — der Status bleibt unangetastet
     * (Übergänge laufen über transitionAudit()).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateAudit(IsmsAudit $audit, User $actor, array $attributes): IsmsAudit {
        return DB::transaction(function () use ($audit, $actor, $attributes): IsmsAudit {
            $audit->update([
                'title' => $attributes['title'] ?? $audit->title,
                'norm' => array_key_exists('norm', $attributes) ? $attributes['norm'] : $audit->norm,
                'edition' => array_key_exists('edition', $attributes) ? $attributes['edition'] : $audit->edition,
                'kind' => $attributes['kind'] ?? $audit->kind,
                'planned_on' => array_key_exists('planned_on', $attributes) ? $attributes['planned_on'] : $audit->planned_on,
                'performed_from' => array_key_exists('performed_from', $attributes) ? $attributes['performed_from'] : $audit->performed_from,
                'performed_to' => array_key_exists('performed_to', $attributes) ? $attributes['performed_to'] : $audit->performed_to,
                'lead_auditor_user_id' => array_key_exists('lead_auditor_user_id', $attributes)
                    ? $this->resolveUserId($attributes['lead_auditor_user_id'], (int) $actor->organization_id, 'lead_auditor_user_id')
                    : $audit->lead_auditor_user_id,
                'auditors' => array_key_exists('auditors', $attributes) ? $attributes['auditors'] : $audit->auditors,
                'criteria' => array_key_exists('criteria', $attributes) ? $attributes['criteria'] : $audit->criteria,
                'independence_note' => array_key_exists('independence_note', $attributes) ? $attributes['independence_note'] : $audit->independence_note,
                'summary' => array_key_exists('summary', $attributes) ? $attributes['summary'] : $audit->summary,
            ]);

            return $audit;
        });
    }

    /**
     * Statusübergang entlang {@see AuditStatus::allowedTransitions()} —
     * reportIssued NUR mit Durchführungszeitraum (performed_from/to) und
     * Ergebnis-Zusammenfassung (Serviceregel).
     *
     * @throws ValidationException
     */
    public function transitionAudit(IsmsAudit $audit, AuditStatus $target, User $actor): IsmsAudit {
        if ($audit->status === $target) {
            return $audit;
        }

        if (! in_array($target, $audit->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.invalid_transition', [
                    'from' => $audit->status->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        if ($target === AuditStatus::ReportIssued
            && ($audit->performed_from === null || $audit->performed_to === null
                || trim((string) $audit->summary) === '')) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.audit_report_requires_result'),
            ]);
        }

        return DB::transaction(function () use ($audit, $target, $actor): IsmsAudit {
            $from = $audit->status;
            $audit->update(['status' => $target->value]);
            $audit->audit('isms.audit.transitioned', [
                'actor_user_id' => $actor->id,
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $audit;
        });
    }

    /**
     * Soft-Delete inkl. Feststellungen und Korrekturmaßnahmen (damit z. B.
     * der Fristen-Scanner keine Maßnahmen gelöschter Audits mehr meldet).
     */
    public function deleteAudit(IsmsAudit $audit, User $actor): void {
        DB::transaction(function () use ($audit, $actor): void {
            $audit->audit('isms.audit.deleted', ['actor_user_id' => $actor->id]);

            foreach ($audit->findings()->get() as $finding) {
                $finding->correctiveActions()->get()->each->delete();
                $finding->delete();
            }

            $audit->delete();
        });
    }

    // ── Feststellungen ─────────────────────────────────────────────────────

    /**
     * Erfasst eine Feststellung — NUR bei laufendem Audit
     * (Status inProgress/reportIssued); Status startet immer bei open.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function createFinding(IsmsAudit $audit, User $actor, array $attributes): IsmsAuditFinding {
        if (! $audit->status->allowsFindings()) {
            throw ValidationException::withMessages([
                'audit' => __('isms.error.finding_requires_running_audit'),
            ]);
        }

        return DB::transaction(function () use ($audit, $actor, $attributes): IsmsAuditFinding {
            unset($actor);

            return IsmsAuditFinding::query()->create([
                'organization_id' => $audit->organization_id,
                'isms_audit_id' => $audit->id,
                'finding_no' => $this->nextFindingNo((int) $audit->id),
                'kind' => $attributes['kind'],
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'isms_requirement_id' => $this->resolveRequirementId($attributes['isms_requirement_id'] ?? null),
                'status' => FindingStatus::Open->value,
            ]);
        });
    }

    /**
     * Aktualisiert Stammdaten einer Feststellung — Status bleibt
     * unangetastet (Übergänge über transitionFinding()).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateFinding(IsmsAuditFinding $finding, User $actor, array $attributes): IsmsAuditFinding {
        return DB::transaction(function () use ($finding, $actor, $attributes): IsmsAuditFinding {
            unset($actor);

            $finding->update([
                'kind' => $attributes['kind'] ?? $finding->kind,
                'title' => $attributes['title'] ?? $finding->title,
                'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $finding->description,
                'isms_requirement_id' => array_key_exists('isms_requirement_id', $attributes)
                    ? $this->resolveRequirementId($attributes['isms_requirement_id'])
                    : $finding->isms_requirement_id,
            ]);

            return $finding;
        });
    }

    /**
     * Statusübergang entlang {@see FindingStatus::allowedTransitions()}.
     * Abschlussregel (closed): alle Korrekturmaßnahmen müssen done oder
     * effective sein; bei Nichtkonformitäten (major/minor) muss zusätzlich
     * mindestens EINE Maßnahme existieren und effective sein —
     * Beobachtungen/Verbesserungen sind auch ohne Maßnahmen schließbar.
     *
     * @throws ValidationException
     */
    public function transitionFinding(IsmsAuditFinding $finding, FindingStatus $target, User $actor): IsmsAuditFinding {
        if ($finding->status === $target) {
            return $finding;
        }

        if (! in_array($target, $finding->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.invalid_transition', [
                    'from' => $finding->status->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        if ($target === FindingStatus::Closed) {
            $this->assertFindingClosable($finding);
        }

        return DB::transaction(function () use ($finding, $target, $actor): IsmsAuditFinding {
            $from = $finding->status;
            $finding->update(['status' => $target->value]);
            $finding->audit('isms.finding.transitioned', [
                'actor_user_id' => $actor->id,
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $finding;
        });
    }

    /** Soft-Delete inkl. Korrekturmaßnahmen. */
    public function deleteFinding(IsmsAuditFinding $finding, User $actor): void {
        DB::transaction(function () use ($finding, $actor): void {
            $finding->audit('isms.finding.deleted', ['actor_user_id' => $actor->id]);
            $finding->correctiveActions()->get()->each->delete();
            $finding->delete();
        });
    }

    // ── Korrekturmaßnahmen ─────────────────────────────────────────────────

    /**
     * Legt eine Korrekturmaßnahme an (Status immer open) — nur solange die
     * Feststellung nicht geschlossen ist.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function createAction(IsmsAuditFinding $finding, User $actor, array $attributes): IsmsCorrectiveAction {
        if ($finding->status === FindingStatus::Closed) {
            throw ValidationException::withMessages([
                'finding' => __('isms.error.action_on_closed_finding'),
            ]);
        }

        return DB::transaction(function () use ($finding, $actor, $attributes): IsmsCorrectiveAction {
            return IsmsCorrectiveAction::query()->create([
                'organization_id' => $finding->organization_id,
                'isms_audit_finding_id' => $finding->id,
                'title' => $attributes['title'],
                'root_cause' => $attributes['root_cause'] ?? null,
                'action_plan' => $attributes['action_plan'] ?? null,
                'owner_user_id' => $this->resolveUserId($attributes['owner_user_id'] ?? null, (int) $actor->organization_id, 'owner_user_id'),
                'due_on' => $attributes['due_on'] ?? null,
                'status' => CorrectiveActionStatus::Open->value,
            ]);
        });
    }

    /**
     * Aktualisiert Stammdaten einer Korrekturmaßnahme — Status bleibt
     * unangetastet (Übergänge über transitionAction()).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateAction(IsmsCorrectiveAction $action, User $actor, array $attributes): IsmsCorrectiveAction {
        return DB::transaction(function () use ($action, $actor, $attributes): IsmsCorrectiveAction {
            $action->update([
                'title' => $attributes['title'] ?? $action->title,
                'root_cause' => array_key_exists('root_cause', $attributes) ? $attributes['root_cause'] : $action->root_cause,
                'action_plan' => array_key_exists('action_plan', $attributes) ? $attributes['action_plan'] : $action->action_plan,
                'owner_user_id' => array_key_exists('owner_user_id', $attributes)
                    ? $this->resolveUserId($attributes['owner_user_id'], (int) $actor->organization_id, 'owner_user_id')
                    : $action->owner_user_id,
                'due_on' => array_key_exists('due_on', $attributes) ? $attributes['due_on'] : $action->due_on,
            ]);

            return $action;
        });
    }

    /**
     * Statusübergang entlang {@see CorrectiveActionStatus::allowedTransitions()}:
     * - done setzt completed_on (heute, falls nicht mitgegeben);
     * - effective/ineffective (Wirksamkeitsprüfung) erfordern die
     *   Pflicht-Notiz effectiveness_note;
     * - ineffective setzt die zugehörige Feststellung zurück auf
     *   inCorrection (auditiert, sofern nicht bereits inCorrection).
     *
     * @throws ValidationException
     */
    public function transitionAction(
        IsmsCorrectiveAction $action,
        CorrectiveActionStatus $target,
        User $actor,
        ?string $effectivenessNote = null,
    ): IsmsCorrectiveAction {
        if ($action->status === $target) {
            return $action;
        }

        if (! in_array($target, $action->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.invalid_transition', [
                    'from' => $action->status->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        $isEffectivenessCheck = in_array($target, [CorrectiveActionStatus::Effective, CorrectiveActionStatus::Ineffective], true);
        if ($isEffectivenessCheck && trim((string) $effectivenessNote) === '') {
            throw ValidationException::withMessages([
                'effectiveness_note' => __('isms.error.effectiveness_note_required'),
            ]);
        }

        return DB::transaction(function () use ($action, $target, $actor, $effectivenessNote, $isEffectivenessCheck): IsmsCorrectiveAction {
            $from = $action->status;

            $action->update([
                'status' => $target->value,
                'completed_on' => $target === CorrectiveActionStatus::Done
                    ? ($action->completed_on ?? Carbon::today())
                    : $action->completed_on,
                'effectiveness_note' => $isEffectivenessCheck
                    ? trim((string) $effectivenessNote)
                    : $action->effectiveness_note,
            ]);

            $action->audit('isms.corrective_action.transitioned', [
                'actor_user_id' => $actor->id,
                'from' => $from->value,
                'to' => $target->value,
            ]);

            // Unwirksame Maßnahme: Feststellung zurück in die Korrektur
            // (bewusste Service-Rücksetzung außerhalb der Nutzer-Kette).
            if ($target === CorrectiveActionStatus::Ineffective) {
                $finding = $action->finding()->first();
                if ($finding !== null && $finding->status !== FindingStatus::InCorrection) {
                    $findingFrom = $finding->status;
                    $finding->update(['status' => FindingStatus::InCorrection->value]);
                    $finding->audit('isms.finding.reverted_to_correction', [
                        'actor_user_id' => $actor->id,
                        'from' => $findingFrom->value,
                        'to' => FindingStatus::InCorrection->value,
                        'corrective_action_id' => $action->id,
                    ]);
                }
            }

            return $action;
        });
    }

    /** Soft-Delete einer Korrekturmaßnahme. */
    public function deleteAction(IsmsCorrectiveAction $action, User $actor): void {
        DB::transaction(function () use ($action, $actor): void {
            $action->audit('isms.corrective_action.deleted', ['actor_user_id' => $actor->id]);
            $action->delete();
        });
    }

    // ── Managementbewertung ────────────────────────────────────────────────

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
                'review_no' => $this->nextNo(IsmsManagementReview::class, 'review_no', (int) $creator->organization_id),
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

    // ── interne Helfer ─────────────────────────────────────────────────────

    /**
     * Abschlussregel für Feststellungen (siehe transitionFinding()).
     *
     * @throws ValidationException
     */
    private function assertFindingClosable(IsmsAuditFinding $finding): void {
        $statuses = $finding->correctiveActions()->get()->map(
            fn(IsmsCorrectiveAction $action): CorrectiveActionStatus => $action->status,
        );

        $allCompleted = $statuses->every(
            fn(CorrectiveActionStatus $status): bool => $status->isCompleted(),
        );

        if (! $allCompleted) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.finding_close_requires_completed_actions'),
            ]);
        }

        /** @var FindingKind $kind */
        $kind = $finding->kind;
        if ($kind->isNonconformity()
            && ! $statuses->contains(CorrectiveActionStatus::Effective)) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.nonconformity_close_requires_effective_action'),
            ]);
        }
    }

    /** @throws ValidationException wenn die Bewertung bereits freigegeben ist */
    private function assertReviewMutable(IsmsManagementReview $review): void {
        if ($review->isApproved()) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.review_already_approved'),
            ]);
        }
    }

    /**
     * Nächste laufende Nummer je Organisation (innerhalb der Transaktion,
     * Muster RiskService::nextRiskNo()).
     *
     * @param  class-string<IsmsAudit|IsmsManagementReview>  $model
     */
    private function nextNo(string $model, string $column, int $organizationId): int {
        $max = $model::query()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->lockForUpdate()
            ->max($column);

        return ((int) $max) + 1;
    }

    /** Nächste laufende Feststellungs-Nummer innerhalb eines Audits. */
    private function nextFindingNo(int $auditId): int {
        $max = IsmsAuditFinding::query()
            ->withTrashed()
            ->where('isms_audit_id', $auditId)
            ->lockForUpdate()
            ->max('finding_no');

        return ((int) $max) + 1;
    }

    /**
     * Löst die optionale Anforderungs-Referenz org-sicher auf: die
     * org-gescopte Requirement-Query (BelongsToOrganization) sieht fremde
     * Anforderungen nicht — unbekannte/fremde IDs werden abgewiesen.
     *
     * @throws ValidationException bei unbekannter/fremder Anforderung
     */
    private function resolveRequirementId(mixed $value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $id = is_numeric($value) ? (int) $value : null;
        $requirement = $id !== null && $id > 0 ? IsmsRequirement::query()->whereKey($id)->first() : null;

        if ($requirement === null) {
            throw ValidationException::withMessages([
                'isms_requirement_id' => __('validation.exists', ['attribute' => __('isms.field.requirement')]),
            ]);
        }

        return (int) $requirement->id;
    }

    /**
     * Löst eine optionale User-Referenz org-sicher auf (User trägt kein
     * BelongsToOrganization — explizite organization_id-Prüfung).
     *
     * @throws ValidationException bei unbekanntem/fremdem User
     */
    private function resolveUserId(mixed $value, int $organizationId, string $field): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $id = is_numeric($value) ? (int) $value : null;
        $user = $id !== null && $id > 0
            ? User::query()->whereKey($id)->where('organization_id', $organizationId)->first()
            : null;

        if ($user === null) {
            throw ValidationException::withMessages([
                $field => __('validation.exists', ['attribute' => __('isms.field.owner')]),
            ]);
        }

        return (int) $user->id;
    }
}
