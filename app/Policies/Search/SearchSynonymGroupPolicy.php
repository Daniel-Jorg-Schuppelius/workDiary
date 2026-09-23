<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchSynonymGroupPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Such-Synonyme wirken auf die Suche der ganzen Organisation — Pflege auf
 * der Stufe der Organisationseinstellungen (`organization.update`).
 */
class SearchSynonymGroupPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::OrganizationUpdate,
        'view' => P::OrganizationUpdate,
        'create' => P::OrganizationUpdate,
        'update' => P::OrganizationUpdate,
        'delete' => P::OrganizationUpdate,
    ];
}
