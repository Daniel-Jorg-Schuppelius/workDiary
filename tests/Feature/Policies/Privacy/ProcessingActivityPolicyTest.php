<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessingActivityPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\Privacy;

use App\Models\Organization;
use App\Models\Privacy\ProcessingActivity;
use App\Policies\Privacy\ProcessingActivityPolicy;
use App\Services\Privacy\DataProtectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * VVT (Verzeichnis von Verarbeitungstätigkeiten, Feature 043): bewusst OHNE
 * Admin-Bypass — die dataprotection.*-Rechte liegen außerhalb des zentralen
 * Permission-Enums und gehen NIE automatisch an (Plattform-)Admins. Bearbeiten
 * (ropa.manage) und Freigeben (ropa.approve) sind getrennte Rechte
 * (Vier-Augen-Prinzip); jeder Objektzugriff ist organisationsgebunden.
 */
final class ProcessingActivityPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private ProcessingActivityPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        DataProtectionPermissions::ensurePermissionsExist();
        $this->actAsTeam($this->organization);
        $this->policy = new ProcessingActivityPolicy;
    }

    private function activity(?int $orgId = null): ProcessingActivity {
        $activity = new ProcessingActivity;
        $activity->organization_id = $orgId ?? $this->organization->id;

        return $activity;
    }

    public function test_viewer_can_read_but_not_write(): void {
        $viewer = $this->actorIn($this->organization, ['dataprotection.view']);
        $activity = $this->activity();

        $this->assertTrue($this->policy->viewAny($viewer));
        $this->assertTrue($this->policy->view($viewer, $activity));
        $this->assertFalse($this->policy->create($viewer));
        $this->assertFalse($this->policy->update($viewer, $activity));
        $this->assertFalse($this->policy->approve($viewer, $activity));
        $this->assertFalse($this->policy->export($viewer));
    }

    public function test_manager_may_edit_but_not_approve_four_eyes(): void {
        $manager = $this->actorIn($this->organization, ['dataprotection.ropa.manage']);
        $activity = $this->activity();

        $this->assertTrue($this->policy->create($manager));
        $this->assertTrue($this->policy->update($manager, $activity));
        // Vier-Augen: Bearbeiter darf NICHT freigeben.
        $this->assertFalse($this->policy->approve($manager, $activity));
    }

    public function test_approver_may_approve_but_not_edit(): void {
        $approver = $this->actorIn($this->organization, ['dataprotection.ropa.approve']);
        $activity = $this->activity();

        $this->assertTrue($this->policy->approve($approver, $activity));
        $this->assertFalse($this->policy->update($approver, $activity));
    }

    public function test_export_requires_dedicated_permission(): void {
        $exporter = $this->actorIn($this->organization, ['dataprotection.export']);

        $this->assertTrue($this->policy->export($exporter));
        $this->assertFalse($this->policy->viewAny($exporter));
    }

    public function test_foreign_org_is_always_denied_even_with_all_permissions(): void {
        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, DataProtectionPermissions::ALL);
        $activity = $this->activity(); // gehört zur Primär-Org

        $this->actAsTeam($foreignOrg); // Angreifer agiert im eigenen Org-Kontext
        $this->assertFalse($this->policy->view($attacker, $activity));
        $this->assertFalse($this->policy->update($attacker, $activity));
        $this->assertFalse($this->policy->approve($attacker, $activity));
    }

    public function test_admins_have_no_bypass(): void {
        $orgAdmin = \App\Models\User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $platformAdmin = \App\Models\User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $activity = $this->activity();

        foreach ([$orgAdmin, $platformAdmin] as $admin) {
            $this->assertTrue(Gate::forUser($admin)->denies('viewAny', ProcessingActivity::class));
            $this->assertTrue(Gate::forUser($admin)->denies('view', $activity));
            $this->assertTrue(Gate::forUser($admin)->denies('update', $activity));
            $this->assertTrue(Gate::forUser($admin)->denies('approve', $activity));
        }
    }

    public function test_orgless_user_is_denied(): void {
        $orgless = $this->orglessActor();
        $activity = $this->activity();

        $this->assertFalse($this->policy->viewAny($orgless));
        $this->assertFalse($this->policy->view($orgless, $activity));
        $this->assertFalse($this->policy->update($orgless, $activity));
    }
}
