<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\User\UserRole;
use App\Models\{Classification, User};
use App\Policies\ClassificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ClassificationPolicyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ClassificationPolicy $policy;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->policy = new ClassificationPolicy;
    }

    public function test_teamleitung_can_manage_org_classifications_but_not_platform_defaults(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->assignRoleWithinOrganization($user, UserRole::Teamleitung->value);

        $orgClassification = Classification::factory()
            ->forOrganization($this->organization->id)
            ->domain(ClassificationDomain::Activity)
            ->create();
        $platformClassification = Classification::factory()
            ->platformDefault()
            ->domain(ClassificationDomain::Activity)
            ->create();

        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $orgClassification));
        $this->assertTrue($this->policy->delete($user, $orgClassification));
        $this->assertTrue($this->policy->deactivateDefault($user));
        $this->assertTrue($this->policy->import($user));

        $this->assertFalse($this->policy->update($user, $platformClassification));
        $this->assertFalse($this->policy->delete($user, $platformClassification));
    }

    public function test_user_role_is_read_only_for_classifications(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->assignRoleWithinOrganization($user, UserRole::User->value);

        $orgClassification = Classification::factory()
            ->forOrganization($this->organization->id)
            ->domain(ClassificationDomain::EntryType)
            ->create();

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $orgClassification));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $orgClassification));
        $this->assertFalse($this->policy->delete($user, $orgClassification));
        $this->assertFalse($this->policy->deactivateDefault($user));
        $this->assertFalse($this->policy->import($user));
    }

    private function assignRoleWithinOrganization(User $user, string $role): void {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);

        $orgRole = Role::query()
            ->where('name', $role)
            ->where('team_id', $this->organization->id)
            ->firstOrFail();

        $user->syncRoles([$orgRole]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
    }
}
