<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StaffPlanningPolicyMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\{AvailabilityWindow, CoverageRequirement, DesiredShift, Organization, User};
use App\Policies\{AvailabilityWindowPolicy, CoverageRequirementPolicy, DesiredShiftPolicy};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Personalplanung: Verfügbarkeitsfenster + Wunschschichten sind strikt
 * eigentümergebunden (availability.manage.own); die Personaleinsatzplanung
 * (staffing.suggest) liest fremde Einträge NUR innerhalb der eigenen
 * Organisation. Besetzungsanforderungen sind read-only (Pflege nur Admin).
 */
final class StaffPlanningPolicyMatrixTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
    }

    /** @return array<string, array{class-string, class-string<Model>}> */
    public static function ownershipPolicies(): array {
        return [
            'availability-window' => [AvailabilityWindowPolicy::class, AvailabilityWindow::class],
            'desired-shift' => [DesiredShiftPolicy::class, DesiredShift::class],
        ];
    }

    /** @param class-string<Model> $modelClass */
    private function subject(string $modelClass, User $owner, ?int $orgId = null): Model {
        /** @var Model&object{organization_id: int|null, user_id: int|null} $subject */
        $subject = new $modelClass;
        $subject->organization_id = $orgId ?? $this->organization->id;
        $subject->user_id = $owner->id;

        return $subject;
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('ownershipPolicies')]
    public function test_owner_manages_own_entries_only(string $policyClass, string $modelClass): void {
        $policy = new $policyClass;
        $owner = $this->actorIn($this->organization, [P::AvailabilityManageOwn]);
        $colleague = $this->actorIn($this->organization, [P::AvailabilityManageOwn]);
        $subject = $this->subject($modelClass, $owner);

        $this->assertTrue($policy->viewAny($owner));
        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->view($owner, $subject));
        $this->assertTrue($policy->update($owner, $subject));
        $this->assertTrue($policy->delete($owner, $subject));

        // Kollege mit demselben Recht: fremde Einträge sind tabu.
        $this->assertFalse($policy->view($colleague, $subject));
        $this->assertFalse($policy->update($colleague, $subject));
        $this->assertFalse($policy->delete($colleague, $subject));
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('ownershipPolicies')]
    public function test_staffing_planner_reads_same_org_only(string $policyClass, string $modelClass): void {
        $policy = new $policyClass;
        $owner = $this->actorIn($this->organization, [P::AvailabilityManageOwn]);
        $planner = $this->actorIn($this->organization, [P::StaffingSuggest]);
        $subject = $this->subject($modelClass, $owner);

        $this->assertTrue($policy->viewAny($planner));
        $this->assertTrue($policy->view($planner, $subject));
        $this->assertFalse($policy->update($planner, $subject), 'Planung liest nur, ändert nie.');

        $foreignOrg = Organization::factory()->create();
        $foreignPlanner = $this->actorIn($foreignOrg, [P::StaffingSuggest]);
        $this->actAsTeam($foreignOrg);
        $this->assertFalse($policy->view($foreignPlanner, $subject), 'staffing.suggest endet an der Org-Grenze.');
    }

    public function test_coverage_requirements_are_read_only_and_org_bound(): void {
        $policy = new CoverageRequirementPolicy;
        $user = $this->actorIn($this->organization);
        $requirement = new CoverageRequirement;
        $requirement->organization_id = $this->organization->id;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $requirement));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, $requirement));
        $this->assertFalse($policy->delete($user, $requirement));

        $foreign = new CoverageRequirement;
        $foreign->organization_id = Organization::factory()->create()->id;
        $this->assertFalse($policy->view($user, $foreign));

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $this->assertTrue(Gate::forUser($admin)->allows('create', CoverageRequirement::class), 'Pflege nur über Admin-Bypass.');
    }
}
