<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildsPolicyActors.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\{Organization, User};
use BackedEnum;
use Spatie\Permission\PermissionRegistrar;

/**
 * Helfer für Policy-Unit-Tests (B7 / MVP-348): baut Nutzer mit korrektem
 * Spatie-Team-Kontext (setPermissionsTeamId!) und direkten Permissions.
 *
 * Fallstrick, den dieser Trait kapselt: Permissions werden IMMER im
 * Team-Kontext der Organisation des Nutzers vergeben — sonst landet die
 * Zuweisung im globalen Kontext (team_id NULL) und der Policy-Check
 * (der unter der aktiven Organisation läuft) sieht sie nicht.
 */
trait BuildsPolicyActors {
    /**
     * Nutzer in einer Organisation mit direkten Permissions anlegen.
     *
     * @param  list<BackedEnum|string>  $permissions
     */
    protected function actorIn(Organization $organization, array $permissions = []): User {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $this->grantPermissions($user, $permissions);

        return $user;
    }

    /**
     * Permissions im Team-Kontext der Nutzer-Organisation vergeben; der
     * vorherige Team-Kontext wird wiederhergestellt.
     *
     * @param  list<BackedEnum|string>  $permissions
     */
    protected function grantPermissions(User $user, array $permissions): void {
        if ($permissions === []) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($user->organization_id);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission instanceof BackedEnum ? $permission->value : $permission);
        }

        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $registrar->setPermissionsTeamId($previous);
    }

    /** Aktiven Spatie-Team-Kontext setzen (Sicht des angreifenden/aktiven Nutzers). */
    protected function actAsTeam(Organization|int|null $organization): void {
        $id = $organization instanceof Organization ? $organization->id : $organization;
        app(PermissionRegistrar::class)->setPermissionsTeamId($id);
    }

    /** Nutzer ohne Organisation (z. B. frisch provisioniert) — muss überall abgewiesen werden. */
    protected function orglessActor(): User {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => null]);

        return $user;
    }

    /**
     * Team-Kontext nach jeder Testmethode zurücksetzen, damit er nicht in
     * andere Testklassen leakt (Muster aus Whistleblowing/CasePolicyTest).
     */
    protected function tearDownBuildsPolicyActors(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}
