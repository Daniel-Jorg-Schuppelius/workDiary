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
    /**
     * Legt die Permissions global (team-unabhaengig, guard web) an. Idempotent.
     * Fast-Path wie im PermissionsSeeder: EINE whereIn-Query, nur Fehlendes
     * anlegen — findOrCreate je Name lud sonst je Aufruf den Spatie-Cache.
     */
    public static function ensurePermissionsExist(): void {
        $existing = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', static::ALL)
            ->pluck('name')
            ->all();

        $missing = array_diff(static::ALL, $existing);
        if ($missing === []) {
            return;
        }

        // Bulk-Insert statt findOrCreate-Schleife: findOrCreate baut je
        // Aufruf den kompletten Spatie-Cache neu auf (Messung 2026-08-19:
        // ~90 ms je Permission). Ein Cache-Reset am Ende genügt.
        $now = now();
        Permission::query()->insert(array_map(static fn (string $name): array => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values($missing)));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
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
        // Direkter Pivot-Sync + EIN Cache-Reset (s. FastPermissionSync).
        \App\Support\FastPermissionSync::syncRole($role, static::ALL);
        $registrar->forgetCachedPermissions();
    }
}
