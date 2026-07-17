<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignsSequentialNo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms\Concerns;

use App\Models\Isms\{IsmsAudit, IsmsAuditFinding, IsmsAuditPackage, IsmsManagementReview, IsmsRisk, IsmsRiskAssessment, IsmsSecurityIncident, IsmsSupplierAssessment, IsmsVulnerability};

/**
 * Laufende Nummern für ISMS-Register (Vergabe innerhalb der Transaktion).
 * Scope-Spalte je nach Register organization_id oder Parent-FK
 * (isms_risk_id/isms_audit_id) — generalisiert im Refactoring Welle 3, B3.
 */
trait AssignsSequentialNo {
    /**
     * Nächste laufende Nummer je Scope (withTrashed + lockForUpdate, Start bei 1).
     *
     * @param  class-string<IsmsAudit|IsmsAuditFinding|IsmsAuditPackage|IsmsManagementReview|IsmsRisk|IsmsRiskAssessment|IsmsSecurityIncident|IsmsSupplierAssessment|IsmsVulnerability>  $model
     */
    private function nextNo(string $model, string $column, string $scopeColumn, int $scopeId): int {
        $max = $model::query()
            ->withTrashed()
            ->where($scopeColumn, $scopeId)
            ->lockForUpdate()
            ->max($column);

        return ((int) $max) + 1;
    }
}
