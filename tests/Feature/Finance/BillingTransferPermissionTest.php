<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferPermissionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\TransferChannel;
use App\Enums\User\Permission;
use App\Models\{Customer, Organization, User};
use App\Models\Finance\BillingTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rollen-/Policy-Matrix der Finanzschnittstelle (Feature 045):
 * admin = alles; buchhaltung = viewAny + transfer.time/material (KEIN config);
 * geschaeftsfuehrung = nur viewAny; user = nichts. Schreibaktionen sind
 * kanal-spezifisch (BillingTransferPolicy).
 */
class BillingTransferPermissionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function makeTransfer(TransferChannel $channel = TransferChannel::Time): BillingTransfer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        return BillingTransfer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'channel' => $channel,
        ]);
    }

    public function test_admin_has_all_finance_permissions(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($admin);

        $this->assertTrue($admin->can(Permission::FinanceViewAny->value));
        $this->assertTrue($admin->can(Permission::FinanceConfig->value));
        $this->assertTrue($admin->can(Permission::FinanceTransferTime->value));
        $this->assertTrue($admin->can(Permission::FinanceTransferMaterial->value));
    }

    public function test_buchhaltung_can_transfer_but_not_configure(): void {
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($accountant);

        $this->assertTrue($accountant->can(Permission::FinanceViewAny->value));
        $this->assertTrue($accountant->can(Permission::FinanceTransferTime->value));
        $this->assertTrue($accountant->can(Permission::FinanceTransferMaterial->value));
        $this->assertFalse($accountant->can(Permission::FinanceConfig->value));

        $transfer = $this->makeTransfer();
        $this->assertTrue(Gate::forUser($accountant)->allows('view', $transfer));
        $this->assertTrue(Gate::forUser($accountant)->allows('confirm', $transfer));
    }

    public function test_geschaeftsfuehrung_is_read_only(): void {
        $exec = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($exec);

        $this->assertTrue($exec->can(Permission::FinanceViewAny->value));
        $this->assertFalse($exec->can(Permission::FinanceConfig->value));
        $this->assertFalse($exec->can(Permission::FinanceTransferTime->value));
        $this->assertFalse($exec->can(Permission::FinanceTransferMaterial->value));

        $transfer = $this->makeTransfer();
        $this->assertTrue(Gate::forUser($exec)->allows('view', $transfer));
        $this->assertFalse(Gate::forUser($exec)->allows('confirm', $transfer));
        $this->assertFalse(Gate::forUser($exec)->allows('void', $transfer));
    }

    public function test_regular_user_has_no_finance_access(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user);

        $this->assertFalse($user->can(Permission::FinanceViewAny->value));

        $transfer = $this->makeTransfer();
        $this->assertFalse(Gate::forUser($user)->allows('view', $transfer));
        $this->assertFalse(Gate::forUser($user)->allows('confirm', $transfer));
    }

    public function test_policy_create_is_channel_specific(): void {
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($accountant);

        $this->assertTrue(Gate::forUser($accountant)->allows('create', [BillingTransfer::class, TransferChannel::Time]));
        $this->assertTrue(Gate::forUser($accountant)->allows('create', [BillingTransfer::class, TransferChannel::Material]));

        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->assertFalse(Gate::forUser($user)->allows('create', [BillingTransfer::class, TransferChannel::Time]));
    }

    public function test_billing_mode_field_is_dropped_without_finance_config(): void {
        // buchhaltung darf Kunden bearbeiten, aber NICHT den Fakturierungsweg.
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($accountant)->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'currency' => 'EUR',
            'billing_mode' => 'datev',
        ])->assertRedirect();

        $this->assertNull($customer->fresh()->billing_mode);
    }

    public function test_billing_mode_field_is_saved_with_finance_config(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'currency' => 'EUR',
            'billing_mode' => 'datev',
        ])->assertRedirect();

        $this->assertSame('datev', $customer->fresh()->billing_mode?->value);
    }

    public function test_cross_org_transfer_is_not_visible(): void {
        $transferA = $this->makeTransfer();

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);

        $this->assertNull(BillingTransfer::find($transferA->id));
        $this->assertSame(0, BillingTransfer::query()->count());
    }
}
