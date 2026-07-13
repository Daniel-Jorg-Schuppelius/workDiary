<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexEligibilityPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\{FlexEligibility, Organization};
use App\Policies\FlexEligibilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Gleitzeit-Berechtigungen: user.flex.manage, aber IMMER nur für Mitglieder
 * der EIGENEN Organisation — das Ziel-Mitglied (nicht ein Objekt) ist die
 * Cross-Tenant-Fläche. Org-lose Akteure sind hart ausgeschlossen.
 */
final class FlexEligibilityPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private FlexEligibilityPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new FlexEligibilityPolicy;
    }

    public function test_manager_handles_own_org_members(): void {
        $manager = $this->actorIn($this->organization, [P::UserFlexManage]);
        $member = $this->actorIn($this->organization);

        $this->assertTrue($this->policy->viewAny($manager, $member));
        $this->assertTrue($this->policy->create($manager, $member));

        $eligibility = new FlexEligibility;
        $eligibility->user_id = $member->id;
        $eligibility->setRelation('user', $member);
        $this->assertTrue($this->policy->delete($manager, $eligibility));
    }

    public function test_foreign_org_member_is_denied_even_with_permission(): void {
        $manager = $this->actorIn($this->organization, [P::UserFlexManage]);
        $foreignMember = $this->actorIn(Organization::factory()->create());

        $this->assertFalse($this->policy->viewAny($manager, $foreignMember));
        $this->assertFalse($this->policy->create($manager, $foreignMember));

        $eligibility = new FlexEligibility;
        $eligibility->user_id = $foreignMember->id;
        $eligibility->setRelation('user', $foreignMember);
        $this->assertFalse($this->policy->delete($manager, $eligibility));
    }

    public function test_without_permission_or_org_denied(): void {
        $nobody = $this->actorIn($this->organization);
        $member = $this->actorIn($this->organization);
        $this->assertFalse($this->policy->viewAny($nobody, $member));

        $orgless = $this->orglessActor();
        $this->assertFalse($this->policy->viewAny($orgless, $member), 'Org-loser Akteur ist hart ausgeschlossen.');
    }
}
