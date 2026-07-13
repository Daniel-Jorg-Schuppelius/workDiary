<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentCasePolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\Investments;

use App\Enums\User\Permission as P;
use App\Models\Investments\InvestmentCase;
use App\Policies\Investments\InvestmentCasePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Investitionsvorhaben (Feature 069): Pflege mit investment.manage,
 * Budget-/Abweichungsfreigaben mit SEPARATEM Recht investment.approve
 * (Freigabekette je Schwelle) — der Bearbeiter darf nicht selbst freigeben.
 * Mandantengrenze trägt der OrganizationScope (BelongsToOrganization).
 */
final class InvestmentCasePolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private InvestmentCasePolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new InvestmentCasePolicy;
    }

    public function test_viewer_reads_but_does_not_write(): void {
        $viewer = $this->actorIn($this->organization, [P::InvestmentViewAny, P::InvestmentView]);
        $case = new InvestmentCase;

        $this->assertTrue($this->policy->viewAny($viewer));
        $this->assertTrue($this->policy->view($viewer, $case));
        $this->assertFalse($this->policy->create($viewer));
        $this->assertFalse($this->policy->update($viewer, $case));
        $this->assertFalse($this->policy->approve($viewer, $case));
    }

    public function test_manager_edits_but_cannot_approve(): void {
        $manager = $this->actorIn($this->organization, [P::InvestmentManage]);
        $case = new InvestmentCase;

        $this->assertTrue($this->policy->create($manager));
        $this->assertTrue($this->policy->update($manager, $case));
        $this->assertTrue($this->policy->delete($manager, $case), 'Nie beantragte Ideen sind löschbar.');
        // Freigabekette: Bearbeiten-Recht genügt NICHT zum Freigeben.
        $this->assertFalse($this->policy->approve($manager, $case));
    }

    public function test_approver_approves_but_cannot_edit(): void {
        $approver = $this->actorIn($this->organization, [P::InvestmentApprove]);
        $case = new InvestmentCase;

        $this->assertTrue($this->policy->approve($approver, $case));
        $this->assertFalse($this->policy->update($approver, $case));
        $this->assertFalse($this->policy->delete($approver, $case));
    }

    public function test_orgless_or_permissionless_user_is_denied(): void {
        $this->assertFalse($this->policy->viewAny($this->actorIn($this->organization)));
        $this->assertFalse($this->policy->viewAny($this->orglessActor()));
    }
}
