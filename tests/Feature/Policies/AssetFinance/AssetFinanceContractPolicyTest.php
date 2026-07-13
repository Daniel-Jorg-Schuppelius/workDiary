<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceContractPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\AssetFinance;

use App\Enums\User\Permission as P;
use App\Models\AssetFinance\AssetFinanceContract;
use App\Policies\AssetFinance\AssetFinanceContractPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Leasing-/Finanzierungsverträge (Feature 074): Lesen mit assetFinance.view*,
 * Pflege mit assetFinance.manage, Finanzdaten (Raten/Buchung) mit separatem
 * Recht assetFinance.finance. Mandantengrenze trägt der OrganizationScope.
 */
final class AssetFinanceContractPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private AssetFinanceContractPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new AssetFinanceContractPolicy;
    }

    public function test_permission_matrix(): void {
        $contract = new AssetFinanceContract;

        $viewer = $this->actorIn($this->organization, [P::AssetFinanceViewAny, P::AssetFinanceView]);
        $this->assertTrue($this->policy->viewAny($viewer));
        $this->assertTrue($this->policy->view($viewer, $contract));
        $this->assertFalse($this->policy->create($viewer));
        $this->assertFalse($this->policy->update($viewer, $contract));
        $this->assertFalse($this->policy->finance($viewer, $contract));

        $manager = $this->actorIn($this->organization, [P::AssetFinanceManage]);
        $this->assertTrue($this->policy->create($manager));
        $this->assertTrue($this->policy->update($manager, $contract));
        $this->assertFalse($this->policy->finance($manager, $contract), 'Finanzdaten verlangen separates Recht.');

        $financier = $this->actorIn($this->organization, [P::AssetFinanceFinance]);
        $this->assertTrue($this->policy->finance($financier, $contract));
        $this->assertFalse($this->policy->update($financier, $contract));
    }

    public function test_orgless_or_permissionless_user_is_denied(): void {
        $this->assertFalse($this->policy->viewAny($this->actorIn($this->organization)));
        $this->assertFalse($this->policy->viewAny($this->orglessActor()));
    }
}
