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
use App\Models\AssetCompliance\AssetComplianceProfile;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Policy des Prüfmittel-Moduls (Feature 075). Kind-Objekte (Pflichten,
 * Termine, Protokolle, Zertifikate) werden gegen diese Root-Policy
 * autorisiert: manage = Katalog/Pflichten, inspect = Protokolle/Nachweise,
 * release = Ausnahmefreigaben (D12).
 */
class AssetComplianceProfilePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::AssetComplianceViewAny->value);
    }

    public function view(User $user, AssetComplianceProfile $profile): bool {
        return $user->can(P::AssetComplianceView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::AssetComplianceManage->value);
    }

    public function update(User $user, AssetComplianceProfile $profile): bool {
        return $user->can(P::AssetComplianceManage->value);
    }

    /**
     * Prüfungen durchführen und Nachweise erfassen (klassenweite Ability).
     */
    public function inspect(User $user): bool {
        return $user->can(P::AssetComplianceInspect->value);
    }

    /**
     * Befristete Ausnahmefreigaben für Prüfsperren (D12, klassenweit).
     */
    public function release(User $user): bool {
        return $user->can(P::AssetComplianceRelease->value);
    }
}
