<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WageTypeMappingPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Lohnarten-Mapping + Export-Lieferung (A21 · MVP-019): Admin via Bypass;
 * Buchhaltung/Lohnbüro pflegt Zuordnungen und Lieferkonfiguration über
 * wageTypeMapping.viewAny/manage — gleiche Zielgruppe wie Zuschlags- und
 * Kostenstellen-Regeln.
 */
class WageTypeMappingPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::WageTypeMappingViewAny,
        'create' => P::WageTypeMappingManage,
        'update' => P::WageTypeMappingManage,
        'delete' => P::WageTypeMappingManage,
    ];
}
