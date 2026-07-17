<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsAudit;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Zugriffsregeln Audits inkl. Feststellungen und Korrekturmaßnahmen
 * (Feature 046, Inkrement C):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view (Auditprogramm einsehen).
 * - Pflege (CRUD), Statuswechsel und Feststellungs-/Maßnahmen-Verwaltung
 *   nur mit isms.manage. Findings und CorrectiveActions haben bewusst
 *   KEINE eigene Policy — sie werden über manageFindings() am Audit
 *   autorisiert (analog Zertifikate in der IsmsNormStatusPolicy).
 */
class IsmsAuditPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::IsmsViewAny,
        'view' => P::IsmsView,
        'create' => P::IsmsManage,
        'update' => P::IsmsManage,
        'delete' => P::IsmsManage,
        'transition' => P::IsmsManage,
        'manageFindings' => P::IsmsManage,
    ];

    /** Statuswechsel entlang der State-Machine (AuditService). */
    public function transition(User $user, IsmsAudit $audit): bool {
        return $this->allows($user, 'transition');
    }

    /** Feststellungen + Korrekturmaßnahmen dieses Audits verwalten. */
    public function manageFindings(User $user, IsmsAudit $audit): bool {
        return $this->allows($user, 'manageFindings');
    }
}
