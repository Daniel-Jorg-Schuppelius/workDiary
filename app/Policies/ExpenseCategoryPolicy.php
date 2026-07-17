<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseCategoryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Policies\Concerns\HasAdminBypass;

class ExpenseCategoryPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => true, // Kategorienliste lesen dürfen alle (z. B. Select)
        'view' => true,
        'create' => false, // Verwaltung nur Admin (before-Hook)
        'update' => false,
        // Löschen nur Admin (Sicherheitsbefund B7/MVP-348); Nutzungs-Guard
        // (keine Löschung verwendeter Kategorien) bleibt im Controller-destroy().
        'delete' => false,
    ];
}
