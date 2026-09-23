<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TagPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Policies\Concerns\HasAdminBypass;

class TagPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => true,
        'view' => true,
        'create' => true, // Tags darf jeder eingeloggte User ad-hoc anlegen
        'update' => false, // Pflege/Löschen nur Admin (before-Hook)
        'delete' => false,
    ];
}
