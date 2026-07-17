<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Contract;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Policy des allgemeinen Vertrags (Welle D, CLM). Ein einheitliches
 * Vertragsverwaltungsrecht: viewAny/view lesen die Akte, manage pflegt
 * Verträge und Obligationen.
 */
class ContractPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::ContractViewAny,
        'view' => P::ContractView,
        'create' => P::ContractManage,
        'update' => P::ContractManage,
    ];
}
