<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OffersAssignableRoles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Access\Concerns;

use App\Enums\User\UserRole;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

/**
 * Rollen, die im Zugriffsbereich zur Auswahl stehen — eine Stelle für
 * Mitglieder und Gruppen (Konsolidierungs-Audit 2026-10, k3-12: die Regel
 * stand zweimal).
 */
trait OffersAssignableRoles {
    /**
     * Globale und eigene Rollen der Organisation. Eskalationsschutz: die
     * globale Rolle „admin“ (plattformweit) sieht nur ein echter
     * Plattform-Admin; die Serverprüfung beim Speichern bleibt die Grenze.
     *
     * @return Collection<int, Role>
     */
    private function availableRoles(): Collection {
        $organization = $this->currentOrganization();
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        $roles = Role::query()
            ->where(function ($q) use ($teamForeign, $organization): void {
                $q->whereNull($teamForeign)
                    ->orWhere($teamForeign, $organization->id);
            })
            ->orderBy('name')
            ->get();

        $auth = Auth::user();
        if (! ($auth instanceof User && $auth->isAdmin())) {
            $roles = $roles->reject(
                fn (Role $r): bool => $r->name === UserRole::Admin->value && $r->getAttribute($teamForeign) === null
            )->values();
        }

        return $roles;
    }
}
