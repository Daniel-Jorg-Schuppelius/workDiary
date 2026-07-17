<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditRecordService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\AuditStatus;
use App\Models\Isms\{IsmsAudit, IsmsScope};
use App\Models\User;
use App\Services\Isms\Concerns\{AssignsSequentialNo, ResolvesAuditReferences};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aggregat-Service Audits (Feature 046, Inkrement C) — aus dem AuditService
 * herausgelöst (Refactoring Welle 2, B6b). Geschäftsregeln:
 * audit_no laufend je Organisation; Übergänge entlang
 * {@see AuditStatus::allowedTransitions()}; reportIssued NUR mit
 * performed_from/to + summary.
 */
class AuditRecordService {
    use AssignsSequentialNo;
    use ResolvesAuditReferences;

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
                'audit_no' => $this->nextNo(IsmsAudit::class, 'audit_no', 'organization_id', (int) $creator->organization_id),
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
}
