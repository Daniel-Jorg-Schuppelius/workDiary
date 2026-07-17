<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderConnectionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Domain;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * DomainReselling-Verbindung (Feature 083): view = Verbindung/Health sehen,
 * manage = konfigurieren/Zugangsdaten rotieren/Pilot bestätigen.
 */
class DomainProviderConnectionPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::DomainProviderView,
        'view' => P::DomainProviderView,
        'create' => P::DomainProviderManage,
        'update' => P::DomainProviderManage,
        'delete' => P::DomainProviderManage,
    ];
}
