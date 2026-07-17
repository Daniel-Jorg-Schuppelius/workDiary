<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsControlPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Zugriffsregeln ISMS-Maßnahmenkatalog/SoA (Feature 044):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view (SoA einsehen).
 * - Pflege inkl. Annex-A-Katalog-Import nur mit isms.manage.
 */
class IsmsControlPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::IsmsViewAny,
        'view' => P::IsmsView,
        'create' => P::IsmsManage,
        'update' => P::IsmsManage,
        'delete' => P::IsmsManage,
        'import' => P::IsmsManage,
    ];

    /** Annex-A-Katalog laden (idempotenter Import, ControlService). */
    public function import(User $user): bool {
        return $this->allows($user, 'import');
    }
}
