<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceContractPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\AssetFinance;

use App\Enums\User\Permission as P;
use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Policy der Leasing-/Finanzierungsakte (Feature 074). Konditionen, Raten,
 * Restwerte und Fristen sind vertrauliche Finanzdaten: view zeigt die Akte
 * ohne Beträge, finance schaltet die Konditionssicht und -pflege frei.
 */
class AssetFinanceContractPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::AssetFinanceViewAny,
        'view' => P::AssetFinanceView,
        'create' => P::AssetFinanceManage,
        'update' => P::AssetFinanceManage,
        'finance' => P::AssetFinanceFinance,
    ];

    /**
     * Vertrauliche Konditionen (Rate/Restwert/Optionen) sehen und pflegen.
     */
    public function finance(User $user, AssetFinanceContract $contract): bool {
        return $this->allows($user, 'finance');
    }
}
