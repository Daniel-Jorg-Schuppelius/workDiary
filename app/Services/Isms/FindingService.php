<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FindingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{CorrectiveActionStatus, FindingKind, FindingStatus};
use App\Models\Isms\{IsmsAudit, IsmsAuditFinding, IsmsCorrectiveAction};
use App\Models\User;
use App\Services\Isms\Concerns\{AssertsIsmsTransition, AssignsSequentialNo, ResolvesAuditReferences};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aggregat-Service Feststellungen (Feature 046, Inkrement C) — aus dem
 * AuditService herausgelöst (Refactoring Welle 2, B6b). Geschäftsregeln:
 * finding_no laufend je Audit; Anlage nur bei Audit-Status
 * inProgress/reportIssued; Abschluss (closed) NUR, wenn alle
 * Korrekturmaßnahmen done/effective sind — Nichtkonformitäten brauchen
 * zusätzlich mindestens EINE wirksame Maßnahme.
 */
class FindingService {
    use AssertsIsmsTransition;

    use AssignsSequentialNo;
    use ResolvesAuditReferences;

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
                'finding_no' => $this->nextNo(IsmsAuditFinding::class, 'finding_no', 'isms_audit_id', (int) $audit->id),
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

        // Gemeinsamer ISMS-Guard (Vollaudit 2026-07, M44).
        $this->assertIsmsTransition($finding->status, $target);

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

}
