<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsScopePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsScope;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln Geltungsbereiche (Feature 046):
 * - admin: alles (before()-Bypass).
 * - Verwaltung (inkl. Liste) BEWUSST nur mit isms.manage — die
 *   Scope-Verwaltung ist Administrationsfläche, kein Lese-Inhalt
 *   (Lesen der SoA je Scope läuft über die IsmsRequirementPolicy).
 * - Der Default-Scope ist nicht löschbar (Serviceregel im ScopeService,
 *   zusätzlich hier abgesichert).
 */
class IsmsScopePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function view(User $user, IsmsScope $scope): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function create(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function update(User $user, IsmsScope $scope): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function delete(User $user, IsmsScope $scope): bool {
        return ! $scope->is_default && $user->can(P::IsmsManage->value);
    }
}
