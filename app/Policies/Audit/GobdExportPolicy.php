<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GobdExportPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\User;

/**
 * Zugriff auf die GoBD-Z3-Datenträgerüberlassung (Feature 063, MVP-132):
 * ausschließlich das Recht `finance.gobd.export` (Buchhaltung + Admin);
 * Modul-Gating `module.finance` läuft über die `finance.*`-Routen. `viewAny`
 * dient dem Menü (NavGate) und der Seite, `export` dem Download.
 */
class GobdExportPolicy extends PermissionPolicy {
    protected const ABILITIES = [
        'viewAny' => Permission::FinanceGobdExport,
        'export' => Permission::FinanceGobdExport,
    ];

    public function export(User $user): bool {
        return $this->allows($user, 'export');
    }
}
