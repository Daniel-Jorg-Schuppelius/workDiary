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

use App\Enums\Contract\ContractKind;
use App\Enums\User\Permission as P;
use App\Models\Contract\Contract;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;
use App\Services\Hr\PersonnelFilePermissions as HR;

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
        // Kundenvereinbarungen (Feature 157): Signaturanforderung/Gegenzeichnung
        // und Nachweisprüfung als eigene Abilities.
        'signing' => P::ContractSigningManage,
        'review' => P::ContractSigningReview,
    ];

    /** Arbeitsverträge (MVP-939) führt die Personalabteilung über das Personalakten-Recht. */
    public function view(User $user, mixed $model = null): bool {
        return $model instanceof Contract && $model->kind === ContractKind::Employment
            ? $user->hasEffectivePermission(HR::VIEW_ANY)
            : parent::view($user, $model);
    }

    public function update(User $user, mixed $model = null): bool {
        return $model instanceof Contract && $model->kind === ContractKind::Employment
            ? $user->hasEffectivePermission(HR::CREATE)
            : parent::update($user, $model);
    }

    public function signing(User $user, Contract $contract): bool {
        return $contract->kind === ContractKind::Employment
            ? $user->hasEffectivePermission(HR::CREATE)
            : $this->allows($user, 'signing');
    }

    public function review(User $user, Contract $contract): bool {
        return $this->allows($user, 'review');
    }
}
