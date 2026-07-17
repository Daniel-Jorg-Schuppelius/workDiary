<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsNormStatusPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsNormStatus;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Zugriffsregeln Konformitätsstatus + Zertifikatsregister (Feature 046,
 * Inkrement B):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view (Statuskette + Zertifikate einsehen).
 * - Statuswechsel (transition) und Zertifikat-Pflege (addCertificate) nur
 *   mit isms.manage. IsmsCertificates haben bewusst KEINE eigene Policy —
 *   sie werden über addCertificate() hier autorisiert (analog
 *   ApplicabilityStatements in der IsmsRequirementPolicy).
 */
class IsmsNormStatusPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::IsmsViewAny,
        'view' => P::IsmsView,
        'create' => P::IsmsManage,
        'update' => P::IsmsManage,
        'delete' => P::IsmsManage,
        'transition' => P::IsmsManage,
        'addCertificate' => P::IsmsManage,
    ];

    /** Statuswechsel entlang der State-Machine (ConformityService). */
    public function transition(User $user, IsmsNormStatus $status): bool {
        return $this->allows($user, 'transition');
    }

    /** Zertifikat zu einem Konformitätsstatus hinterlegen. */
    public function addCertificate(User $user, IsmsNormStatus $status): bool {
        return $this->allows($user, 'addCertificate');
    }
}
