<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WithOrganization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Concerns;

use App\Models\{Organization, User};
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sets up a default Organization and binds it as 'currentOrganization'
 * in the service container so that BelongsToOrganization / OrganizationScope
 * work correctly inside feature tests.
 *
 * Usage:
 *   use Tests\Concerns\WithOrganization;
 *   // in setUp() or directly in a test:
 *   $this->setUpOrganization();
 *
 * Zusätzlich schlanke Nutzer-Helfer (C19): orgUser()/orgAdmin() für die
 * Standard-Factory-States, userWithRole() für genau EINE per-Org-Rolle.
 * Nur per-Test-Daten, kein Shared State — parallel-tauglich.
 */
trait WithOrganization {
    protected Organization $organization;

    protected function setUpOrganization(?array $attributes = []): void {
        $this->organization = Organization::factory()->create($attributes);
        app()->instance('currentOrganization', $this->organization);
    }

    /**
     * Standard-Nutzer (Factory-State user()) in der Test-Organisation.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function orgUser(array $attributes = []): User {
        /** @var User */
        return User::factory()->user()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
    }

    /**
     * Admin (Factory-State admin()) in der Test-Organisation.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function orgAdmin(array $attributes = []): User {
        /** @var User */
        return User::factory()->admin()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
    }

    /**
     * Nutzer mit einer Rolle — wie die Admin-UI und die Factory-States werden
     * BEIDE gleichnamigen Rollen-Zeilen angehängt (MVP-538-Falle behoben):
     * die PER-ORG-Zeile (team_id = Org) trägt die Permissions für die
     * Policies, die GLOBALE Zeile (team_id = NULL) matcht team-lose
     * Namens-Abfragen wie `User::role([...])` (NotificationDispatcher).
     * Der Spatie-Team-Kontext bleibt auf der Ziel-Organisation
     * stehen (Aufrufer setzen ihn in setUp()).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(string $role, ?Organization $organization = null, array $attributes = []): User {
        $organization ??= $this->organization;

        /** @var User $user */
        $user = User::factory()->create(array_merge(['organization_id' => $organization->id], $attributes));

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($organization->id);

        $roles = Role::query()
            ->where('name', $role)
            ->where('guard_name', 'web')
            ->where(static fn ($q) => $q->whereNull('team_id')->orWhere('team_id', $organization->id))
            ->get();
        if ($roles->where('team_id', $organization->id)->isEmpty()) {
            throw new \RuntimeException("Per-Org-Rolle '{$role}' für Organisation {$organization->id} nicht gefunden.");
        }

        $user->syncRoles($roles->all());
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user;
    }
}
