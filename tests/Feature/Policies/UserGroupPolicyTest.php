<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserGroupPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Organization, UserGroup};
use App\Policies\UserGroupPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Benutzergruppen (Rechteverwaltung): access.manage als Voraussetzung für
 * alles, Objektzugriff hart organisationsgebunden, System-Gruppen sind
 * unlöschbar (is_system-Guard vor allen anderen Prüfungen).
 */
final class UserGroupPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private UserGroupPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new UserGroupPolicy;
    }

    private function group(?int $orgId = null, bool $system = false): UserGroup {
        $group = new UserGroup;
        $group->organization_id = $orgId ?? $this->organization->id;
        $group->is_system = $system;

        return $group;
    }

    public function test_access_manager_may_manage_own_org_groups(): void {
        $manager = $this->actorIn($this->organization, [P::AccessManage]);
        $group = $this->group();

        $this->assertTrue($this->policy->viewAny($manager));
        $this->assertTrue($this->policy->view($manager, $group));
        $this->assertTrue($this->policy->create($manager));
        $this->assertTrue($this->policy->update($manager, $group));
        $this->assertTrue($this->policy->delete($manager, $group));
    }

    public function test_system_groups_are_undeletable(): void {
        $manager = $this->actorIn($this->organization, [P::AccessManage]);

        $this->assertFalse($this->policy->delete($manager, $this->group(null, true)));
    }

    public function test_foreign_org_group_is_denied_even_with_permission(): void {
        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::AccessManage]);
        $group = $this->group(); // Primär-Org

        $this->actAsTeam($foreignOrg);
        $this->assertFalse($this->policy->view($attacker, $group));
        $this->assertFalse($this->policy->update($attacker, $group));
        $this->assertFalse($this->policy->delete($attacker, $group));
    }

    public function test_without_access_manage_denied_and_orgless_cannot_create(): void {
        $nobody = $this->actorIn($this->organization);
        $this->assertFalse($this->policy->viewAny($nobody));
        $this->assertFalse($this->policy->update($nobody, $this->group()));

        $orgless = $this->orglessActor();
        $this->assertFalse($this->policy->create($orgless), 'Org-loser User darf keine Gruppen anlegen.');
    }
}
