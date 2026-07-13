<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\{Customer, Organization, Project, User};
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Projekte: Sichtbarkeit folgt der Organisation des KUNDEN (Fallback:
 * Projekt-Org), Anlegen für Abrechnung/User-Rolle, Ändern nur durch den
 * Ersteller (created_by) — und Löschen ist auf Policy-Ebene IMMER false
 * (nur Admin via Bypass; Guards für Default-/Kind-Projekte liegen davor).
 */
final class ProjectPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private ProjectPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new ProjectPolicy;
    }

    private function project(?int $orgId = null, ?User $creator = null, ?Customer $customer = null): Project {
        $project = new Project;
        $project->organization_id = $orgId ?? $this->organization->id;
        $project->created_by = $creator?->id;
        $project->setRelation('customer', $customer);

        return $project;
    }

    public function test_view_follows_customer_org_with_project_fallback(): void {
        $user = $this->actorIn($this->organization);
        $foreignOrgId = Organization::factory()->create()->id;

        $ownCustomer = new Customer;
        $ownCustomer->organization_id = $this->organization->id;
        $foreignCustomer = new Customer;
        $foreignCustomer->organization_id = $foreignOrgId;

        $this->assertTrue($this->policy->view($user, $this->project(null, null, $ownCustomer)));
        $this->assertFalse($this->policy->view($user, $this->project(null, null, $foreignCustomer)), 'Fremder Kunde ⇒ Projekt unsichtbar, auch wenn Projekt-Org passt.');
        $this->assertTrue($this->policy->view($user, $this->project()), 'Ohne Kunde zählt die Projekt-Org.');
        $this->assertFalse($this->policy->view($user, $this->project($foreignOrgId)));
    }

    public function test_only_creator_updates_without_admin(): void {
        $creator = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $project = $this->project(null, $creator);

        $this->assertTrue($this->policy->create($creator));
        $this->assertTrue($this->policy->create($accountant));
        $this->assertTrue($this->policy->update($creator, $project));
        $this->assertFalse($this->policy->update($accountant, $project), 'Selbst Abrechnung ändert fremde Projekte nicht (nur Admin-Bypass).');
    }

    public function test_delete_is_admin_only_via_bypass(): void {
        $creator = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $project = $this->project(null, $creator);

        $this->assertFalse($this->policy->delete($creator, $project), 'Policy-delete ist hart false — nur Admin-Bypass.');

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $project));
    }

    public function test_orgless_or_roleless_user_cannot_create(): void {
        $this->assertFalse($this->policy->create($this->actorIn($this->organization)), 'Ohne Rolle kein Anlegen.');
        $this->assertFalse($this->policy->create($this->orglessActor()));
    }
}
