<?php
/*
 * Created on   : Tue Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FastPermissionSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Verhaltensgleicher, schneller Ersatz für Spaties `Role::syncPermissions()`
 * beim Organisations-Seeding.
 *
 * Hintergrund (Messung 2026-08-19): `syncPermissions(<Namen>)` löste jeden
 * Namen per O(n)-Suche über die gecachte Permission-Collection auf und warf
 * nach JEDER Rolle den Spatie-Cache weg — bei 465 Permissions und mehreren
 * Rollen je Organisation ~3,7 s reine PHP-Zeit **pro Org-Anlage** (DB-Anteil:
 * 0,2 s). Da jede Test-Methode eine Organisation anlegt, dominierte das die
 * gesamte Test-Suite.
 *
 * Hier stattdessen: EINE Namens→ID-Query, Pivot-Diff direkt auf
 * `role_has_permissions` (DELETE der abgewählten + INSERT der fehlenden —
 * dieselbe Ergebnismenge wie syncPermissions). Der Spatie-Cache wird bewusst
 * NICHT je Rolle invalidiert — der Aufrufer setzt ihn am Ende EINMAL zurück
 * ({@see \Spatie\Permission\PermissionRegistrar::forgetCachedPermissions()}).
 */
final class FastPermissionSync {
    /**
     * @param list<string> $names Permission-Namen (guard web); unbekannte Namen
     *                            werden — anders als bei Spatie — NICHT still
     *                            akzeptiert, sondern lösen eine Exception aus.
     */
    public static function syncRole(Role $role, array $names): void {
        $pivotTable = (string) config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?? 'role_id');
        $permissionKey = (string) (config('permission.column_names.permission_pivot_key') ?? 'permission_id');
        $permissionTable = (string) config('permission.table_names.permissions', 'permissions');

        $ids = DB::table($permissionTable)
            ->where('guard_name', $role->guard_name ?? 'web')
            ->whereIn('name', $names)
            ->pluck('id', 'name');

        $missing = array_diff($names, $ids->keys()->all());
        if ($missing !== []) {
            throw new \RuntimeException('Unbekannte Permissions: ' . implode(', ', $missing));
        }

        $wanted = array_map(intval(...), $ids->values()->all());
        $current = DB::table($pivotTable)
            ->where($roleKey, $role->getKey())
            ->pluck($permissionKey)
            ->map(intval(...))
            ->all();

        $toDelete = array_diff($current, $wanted);
        if ($toDelete !== []) {
            DB::table($pivotTable)
                ->where($roleKey, $role->getKey())
                ->whereIn($permissionKey, $toDelete)
                ->delete();
        }

        $toInsert = array_diff($wanted, $current);
        if ($toInsert !== []) {
            DB::table($pivotTable)->insert(array_map(
                fn (int $id): array => [$roleKey => $role->getKey(), $permissionKey => $id],
                array_values($toInsert),
            ));
        }
    }
}
