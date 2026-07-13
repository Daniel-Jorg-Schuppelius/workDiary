<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectRequestPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\Privacy;

use App\Models\{Organization, User};
use App\Models\Privacy\DataSubjectRequest;
use App\Policies\Privacy\DataSubjectRequestPolicy;
use App\Services\Privacy\DataProtectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Betroffenenanfragen (DSGVO Art. 15 ff.): sensibelste Privacy-Ressource.
 * BEWUSST OHNE Admin-Bypass; Bearbeiten (dsr.manage), Zuweisen (dsr.assign)
 * und Export (dataprotection.export) sind getrennte Rechte; jeder
 * Objektzugriff ist organisationsgebunden.
 */
final class DataSubjectRequestPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private DataSubjectRequestPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        DataProtectionPermissions::ensurePermissionsExist();
        $this->actAsTeam($this->organization);
        $this->policy = new DataSubjectRequestPolicy;
    }

    private function request(?int $orgId = null): DataSubjectRequest {
        $request = new DataSubjectRequest;
        $request->organization_id = $orgId ?? $this->organization->id;

        return $request;
    }

    public function test_manager_may_handle_requests_but_not_assign_or_export(): void {
        $manager = $this->actorIn($this->organization, ['dataprotection.dsr.manage']);
        $request = $this->request();

        $this->assertTrue($this->policy->viewAny($manager));
        $this->assertTrue($this->policy->view($manager, $request));
        $this->assertTrue($this->policy->create($manager));
        $this->assertTrue($this->policy->update($manager, $request));
        $this->assertFalse($this->policy->assign($manager, $request));
        $this->assertFalse($this->policy->export($manager, $request));
    }

    public function test_assign_and_export_are_separate_rights(): void {
        $assigner = $this->actorIn($this->organization, ['dataprotection.dsr.assign']);
        $exporter = $this->actorIn($this->organization, ['dataprotection.export']);
        $request = $this->request();

        $this->assertTrue($this->policy->assign($assigner, $request));
        $this->assertFalse($this->policy->view($assigner, $request));

        $this->assertTrue($this->policy->export($exporter, $request));
        $this->assertFalse($this->policy->update($exporter, $request));
    }

    public function test_general_privacy_viewer_sees_no_requests(): void {
        // dataprotection.view genügt NICHT — Betroffenenanfragen verlangen dsr.manage.
        $viewer = $this->actorIn($this->organization, ['dataprotection.view']);
        $request = $this->request();

        $this->assertFalse($this->policy->viewAny($viewer));
        $this->assertFalse($this->policy->view($viewer, $request));
    }

    public function test_foreign_org_is_always_denied_even_with_all_permissions(): void {
        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, DataProtectionPermissions::ALL);
        $request = $this->request();

        $this->actAsTeam($foreignOrg);
        $this->assertFalse($this->policy->view($attacker, $request));
        $this->assertFalse($this->policy->update($attacker, $request));
        $this->assertFalse($this->policy->assign($attacker, $request));
        $this->assertFalse($this->policy->export($attacker, $request));
    }

    public function test_admins_have_no_bypass(): void {
        $orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $platformAdmin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $request = $this->request();

        foreach ([$orgAdmin, $platformAdmin] as $admin) {
            $this->assertTrue(Gate::forUser($admin)->denies('viewAny', DataSubjectRequest::class));
            $this->assertTrue(Gate::forUser($admin)->denies('view', $request));
            $this->assertTrue(Gate::forUser($admin)->denies('update', $request));
        }
    }

    public function test_orgless_user_is_denied(): void {
        $orgless = $this->orglessActor();

        $this->assertFalse($this->policy->viewAny($orgless));
        $this->assertFalse($this->policy->view($orgless, $this->request()));
    }
}
