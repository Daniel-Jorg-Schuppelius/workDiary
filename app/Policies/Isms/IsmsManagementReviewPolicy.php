<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsManagementReviewPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsManagementReview;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Zugriffsregeln Managementbewertung (Feature 046, Inkrement C):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view (Protokolle einsehen).
 * - Pflege und Freigabe (approve) nur mit isms.manage; die
 *   UNVERÄNDERLICHKEIT freigegebener Bewertungen erzwingt der
 *   AuditService (ValidationException), nicht die Policy.
 */
class IsmsManagementReviewPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::IsmsViewAny,
        'view' => P::IsmsView,
        'create' => P::IsmsManage,
        'update' => P::IsmsManage,
        'delete' => P::IsmsManage,
        'approve' => P::IsmsManage,
    ];

    /** Freigabe (draft → approved, setzt Person + Zeitpunkt). */
    public function approve(User $user, IsmsManagementReview $review): bool {
        return $this->allows($user, 'approve');
    }
}
