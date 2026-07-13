<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasEffectivePermissions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Effektive Permissions des User-Modells: direkte Rechte, Rollen-Rechte und
 * Gruppen-Rechte (inkl. Rollen der Gruppen) zusammengeführt. Aus dem
 * User-Modell extrahiert (Refactoring Welle 2, B6b) — die Relation
 * userGroups() bleibt im Modell, Verhalten unverändert.
 */
trait HasEffectivePermissions {
    /**
     * Alle Permission-Namen, die der User effektiv besitzt:
     *   - direkte Permissions am User,
     *   - Permissions via eigene Rollen,
     *   - Permissions via Gruppen-Mitgliedschaften (eigene Permissions der
     *     Gruppe und Permissions der Rollen, die der Gruppe zugewiesen sind).
     *
     * Wird sowohl von Policies (über {@see hasEffectivePermission()}) als
     * auch von der Admin-UI für die Anzeige verwendet.
     *
     * @return Collection<int, string>
     */
    public function effectivePermissionNames(): Collection {
        /** @var Collection<int, SpatiePermission> $direct */
        $direct = $this->getAllPermissions();
        $names = $direct->pluck('name');

        $this->loadMissing(['userGroups.permissions', 'userGroups.roles.permissions']);

        foreach ($this->userGroups as $group) {
            /** @var Collection<int, SpatiePermission> $groupPermissions */
            $groupPermissions = $group->getAllPermissions();
            foreach ($groupPermissions as $permission) {
                $names->push($permission->name);
            }
        }

        return $names->unique()->values();
    }

    /**
     * Schnelle Prüfung, ob der User die übergebene Permission effektiv
     * besitzt — wird vom Gate::before-Hook in AuthServiceProvider
     * aufgerufen, damit `$user->can('xy')` auch Gruppen-Permissions
     * berücksichtigt.
     */
    public function hasEffectivePermission(string $permission): bool {
        try {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        } catch (PermissionDoesNotExist) {
            return false;
        }

        $this->loadMissing(['userGroups.permissions', 'userGroups.roles.permissions']);

        foreach ($this->userGroups as $group) {
            try {
                if ($group->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                return false;
            }
        }

        return false;
    }
}
