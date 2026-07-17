<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeedsIsolatedPermissionSet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Models\Organization;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed-Logik fuer Permission-Saetze, die BEWUSST von der zentralen
 * {@see \App\Enums\User\Permission}-Enum getrennt sind (kein Auto-Grant an
 * Admins). Nutzende Klassen definieren `ALL` (list<string>) und `ROLE`.
 */
trait SeedsIsolatedPermissionSet {
    /** Legt die Permissions global (team-unabhaengig, guard web) an. Idempotent. */
    public static function ensurePermissionsExist(): void {
        foreach (static::ALL as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /**
     * Legt fuer eine Organisation die Rolle `static::ROLE` mit allen
     * Permissions an (team_id = organization.id). Idempotent.
     */
    public static function seedOrganization(Organization $organization, ?PermissionRegistrar $registrar = null): void {
        $registrar ??= app(PermissionRegistrar::class);
        static::ensurePermissionsExist();

        $registrar->setPermissionsTeamId($organization->id);
        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');

        /** @var Role $role */
        $role = Role::query()->firstOrCreate([
            $teamForeign => $organization->id,
            'name' => static::ROLE,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(static::ALL);
    }
}
