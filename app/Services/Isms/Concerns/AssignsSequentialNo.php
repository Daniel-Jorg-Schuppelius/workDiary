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

use App\Models\Isms\{IsmsAudit, IsmsManagementReview};

/**
 * Laufende Nummern je Organisation (audit_no/review_no) — Vergabe innerhalb
 * der Transaktion, Muster RiskService::nextRiskNo(). Aus dem AuditService
 * extrahiert (Refactoring Welle 2, B6b).
 */
trait AssignsSequentialNo {
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
}
