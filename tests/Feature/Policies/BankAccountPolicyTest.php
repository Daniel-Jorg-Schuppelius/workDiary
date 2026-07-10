<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankAccountPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\Finance\BankAccount;
use App\Models\User;
use App\Policies\Finance\BankAccountPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Eigene Bankkonten (Feature 045): Verwaltung nur mit finance.config,
 * Lesen mit finance.viewAny ODER finance.config. Ohne Finanzrechte: kein
 * Zugriff. (Mandantengrenze trägt der OrganizationScope/Model-Binding, nicht
 * die Policy — daher hier reines Permission-Gating.)
 */
final class BankAccountPolicyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private BankAccountPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->policy = new BankAccountPolicy;
    }

    /** @param list<P> $permissions */
    private function userWith(array $permissions): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission->value);
        }
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $user;
    }

    public function test_finance_config_may_manage(): void {
        $user = $this->userWith([P::FinanceConfig]);
        $account = new BankAccount;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $account));
        $this->assertTrue($this->policy->delete($user, $account));
    }

    public function test_finance_viewany_is_read_only(): void {
        $user = $this->userWith([P::FinanceViewAny]);
        $account = new BankAccount;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $account));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $account));
        $this->assertFalse($this->policy->delete($user, $account));
    }

    public function test_without_finance_permissions_denied(): void {
        $user = $this->userWith([]);

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, new BankAccount));
        $this->assertFalse($this->policy->create($user));
    }
}
