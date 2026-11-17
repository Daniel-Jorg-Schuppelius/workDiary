<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionOrderItemPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\AssetCompliance;

use App\Enums\User\Permission as P;
use App\Models\AssetCompliance\AssetInspectionOrderItem;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Position eines Prüfauftrags: Lesen mit dem Prüfwesen-Leserecht, Ergänzen
 * (Merker für die nächste Periode, Feature 201) wie am Prüftermin mit Pflege-
 * oder Prüfrecht. Die Rückmeldung des Dienstleisters selbst ändert nur der
 * Prüfauftrag.
 */
class AssetInspectionOrderItemPolicy {
    use HasAdminBypass;

    public function view(User $user, AssetInspectionOrderItem $item): bool {
        return (int) $item->organization_id === (int) $user->organization_id
            && $user->can(P::AssetComplianceView->value);
    }

    public function update(User $user, AssetInspectionOrderItem $item): bool {
        return (int) $item->organization_id === (int) $user->organization_id
            && ($user->can(P::AssetComplianceManage->value) || $user->can(P::AssetComplianceInspect->value));
    }
}
