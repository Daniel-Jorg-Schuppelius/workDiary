<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditsAccessChanges.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Models\{User, UserGroup};
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Auditiert Rollen- und Permission-Vergaben an Usern bzw. Benutzergruppen
 * (supportzugriff-grundsaetze.md §4.1, Bauturbo A17/MVP-335):
 *
 *   - `user.role.assigned` / `user.role.revoked`            → Payload `{ role, team_id }`
 *   - `user.permission.granted` / `user.permission.revoked` → Payload `{ permission, team_id }`
 *
 * Der Ziel-User (bzw. die Gruppe, deren Mitglieder die Rechte erben) steht in
 * `auditable_type`/`auditable_id`, der Akteur implizit in `user_id`. Es werden
 * ausschließlich echte Änderungen geschrieben (Sync-Diff) — No-Op-Syncs
 * erzeugen KEINE Events. Schreibweg ist der etablierte Auditable::audit()-Pfad
 * (hash-verkettete audit_logs, nie roh schreiben).
 */
trait AuditsAccessChanges {
    /**
     * Synct die Rollen des Ziels und auditiert die Differenz.
     *
     * @param  array<int, Role|RoleContract|int|string>|Collection<int, Role>|\Illuminate\Database\Eloquent\Collection<int, Role>  $roles
     */
    private function syncRolesAudited(User|UserGroup $target, mixed $roles): void {
        /** @var Collection<int, Role> $before */
        $before = $target->roles()->get();
        $target->syncRoles($roles);
        $target->unsetRelation('roles');
        /** @var Collection<int, Role> $after */
        $after = $target->roles()->get();

        $teamKey = $this->permissionTeamKey();
        foreach ($after->reject(fn (Role $r): bool => $before->contains('id', $r->getKey())) as $role) {
            $target->audit('user.role.assigned', [
                'role' => $role->name,
                'team_id' => $role->getAttribute($teamKey),
            ]);
        }
        foreach ($before->reject(fn (Role $r): bool => $after->contains('id', $r->getKey())) as $role) {
            $target->audit('user.role.revoked', [
                'role' => $role->name,
                'team_id' => $role->getAttribute($teamKey),
            ]);
        }
    }

    /**
     * Synct die Direkt-Permissions des Ziels und auditiert die Differenz.
     * `team_id` ist hier der aktive Team-/Org-Kontext der Vergabe (Permissions
     * selbst sind global, erst die Zuweisung ist team-gebunden).
     *
     * @param  array<int, \Spatie\Permission\Models\Permission|int|string>|Collection<int, \Spatie\Permission\Models\Permission>|\Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission>  $permissions
     */
    private function syncPermissionsAudited(User|UserGroup $target, mixed $permissions): void {
        /** @var Collection<int, \Spatie\Permission\Models\Permission> $before */
        $before = $target->permissions()->get();
        $target->syncPermissions($permissions);
        $target->unsetRelation('permissions');
        /** @var Collection<int, \Spatie\Permission\Models\Permission> $after */
        $after = $target->permissions()->get();

        $teamId = app(PermissionRegistrar::class)->getPermissionsTeamId();
        foreach ($after->reject(fn ($p): bool => $before->contains('id', $p->getKey())) as $permission) {
            $target->audit('user.permission.granted', [
                'permission' => $permission->name,
                'team_id' => $teamId,
            ]);
        }
        foreach ($before->reject(fn ($p): bool => $after->contains('id', $p->getKey())) as $permission) {
            $target->audit('user.permission.revoked', [
                'permission' => $permission->name,
                'team_id' => $teamId,
            ]);
        }
    }

    /**
     * Auditiert eine einzelne, sicher neue Rollen-Zuweisung (z. B. beim
     * Anlegen eines Mitglieds — dort gibt es keinen Vorher-Stand).
     */
    private function auditAssignedRole(User|UserGroup $target, Role|RoleContract $role): void {
        if (! $role instanceof Role) {
            // Spatie liefert konkret immer das Eloquent-Modell; Fremd-
            // Implementierungen des Contracts tragen keine Attribute.
            return;
        }

        $target->audit('user.role.assigned', [
            'role' => $role->name,
            'team_id' => $role->getAttribute($this->permissionTeamKey()),
        ]);
    }

    private function permissionTeamKey(): string {
        return (string) config('permission.column_names.team_foreign_key', 'team_id');
    }
}
