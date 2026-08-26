<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDocumentationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\User\Permission;
use App\Models\User;
use App\Policies\PermissionPolicy;

/**
 * Verfahrensdokumentation (Feature 134): bewusst KEIN neues Recht — dasselbe
 * `finance.gobd.export` wie die Z3-Datenträgerüberlassung (Buchhaltung +
 * Admin); Modul-Gating `module.finance` über die `finance.*`-Routen.
 */
class ProcedureDocumentationPolicy extends PermissionPolicy {
    protected const ABILITIES = [
        'viewAny' => Permission::FinanceGobdExport,
        'view' => Permission::FinanceGobdExport,
        'create' => Permission::FinanceGobdExport,
        'update' => Permission::FinanceGobdExport,
        'delete' => Permission::FinanceGobdExport,
        'publish' => Permission::FinanceGobdExport,
    ];

    public function publish(User $user, mixed $model = null): bool {
        return $this->allows($user, 'publish');
    }
}
