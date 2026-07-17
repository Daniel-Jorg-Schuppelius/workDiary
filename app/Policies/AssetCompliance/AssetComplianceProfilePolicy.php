<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceProfilePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\AssetCompliance;

use App\Enums\User\Permission as P;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Policy des Prüfmittel-Moduls (Feature 075). Kind-Objekte (Pflichten,
 * Termine, Protokolle, Zertifikate) werden gegen diese Root-Policy
 * autorisiert: manage = Katalog/Pflichten, inspect = Protokolle/Nachweise,
 * release = Ausnahmefreigaben (D12).
 */
class AssetComplianceProfilePolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::AssetComplianceViewAny,
        'view' => P::AssetComplianceView,
        'create' => P::AssetComplianceManage,
        'update' => P::AssetComplianceManage,
        'inspect' => P::AssetComplianceInspect,
        'release' => P::AssetComplianceRelease,
    ];

    /**
     * Prüfungen durchführen und Nachweise erfassen (klassenweite Ability).
     */
    public function inspect(User $user): bool {
        return $this->allows($user, 'inspect');
    }

    /**
     * Befristete Ausnahmefreigaben für Prüfsperren (D12, klassenweit).
     */
    public function release(User $user): bool {
        return $this->allows($user, 'release');
    }
}
