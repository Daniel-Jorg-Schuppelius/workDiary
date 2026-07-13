<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DutyPlanPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\Shift\DutyPlanStatus;
use App\Models\{DutyPlan, Organization, User};
use App\Policies\DutyPlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Dienstpläne: Admin-Verwaltung mit einer Besonderheit — der before()-Hook
 * nimmt `delete` vom Admin-Bypass aus: ein VERÖFFENTLICHTER Plan ist auch für
 * Admins unlöschbar (erst zurückziehen). view/update sind organisationsgebunden.
 */
final class DutyPlanPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private DutyPlanPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new DutyPlanPolicy;
    }

    private function plan(DutyPlanStatus $status, ?int $orgId = null): DutyPlan {
        $plan = new DutyPlan;
        $plan->organization_id = $orgId ?? $this->organization->id;
        $plan->status = $status;

        return $plan;
    }

    public function test_admin_manages_plans_but_delete_skips_bypass(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $draft = $this->plan(DutyPlanStatus::Draft);
        $published = $this->plan(DutyPlanStatus::Published);

        $gate = Gate::forUser($admin);
        $this->assertTrue($gate->allows('create', DutyPlan::class));
        $this->assertTrue($gate->allows('update', $draft));
        $this->assertTrue($gate->allows('delete', $draft));
        // Kernvertrag: veröffentlichter Plan ist AUCH für Admins unlöschbar.
        $this->assertTrue($gate->denies('delete', $published));
    }

    public function test_admin_delete_respects_org_boundary(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $foreignOrg = Organization::factory()->create();
        $foreignDraft = $this->plan(DutyPlanStatus::Draft, $foreignOrg->id);

        // delete umgeht den Bypass und prüft sameOrg hart.
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $foreignDraft));
    }

    public function test_regular_user_sees_own_org_plans_only(): void {
        $user = $this->actorIn($this->organization);
        $ownPlan = $this->plan(DutyPlanStatus::Published);
        $foreignPlan = $this->plan(DutyPlanStatus::Published, Organization::factory()->create()->id);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $ownPlan));
        $this->assertFalse($this->policy->view($user, $foreignPlan));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $ownPlan));
        $this->assertFalse($this->policy->delete($user, $this->plan(DutyPlanStatus::Draft)));
    }
}
