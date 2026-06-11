<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingModeResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Finance;

use App\Enums\Finance\BillingMode;
use App\Models\Customer;
use App\Services\Finance\BillingModeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kaskade des Fakturierungswegs (Feature 045):
 * Kunde-Override ?? Org-Setting ?? workdiary.
 */
class BillingModeResolverTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function makeCustomer(?string $override = null): Customer {
        return Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'billing_mode' => $override,
        ]);
    }

    public function test_defaults_to_workdiary_without_override_and_org_setting(): void {
        $customer = $this->makeCustomer();

        $this->assertSame(BillingMode::Workdiary, app(BillingModeResolver::class)->effectiveFor($customer));
    }

    public function test_org_setting_is_used_when_customer_has_no_override(): void {
        $this->organization->update(['settings' => ['billing_mode' => 'lexoffice']]);
        $customer = $this->makeCustomer()->fresh();

        $this->assertSame(BillingMode::Lexoffice, app(BillingModeResolver::class)->effectiveFor($customer));
    }

    public function test_customer_override_wins_over_org_setting(): void {
        $this->organization->update(['settings' => ['billing_mode' => 'lexoffice']]);
        $customer = $this->makeCustomer('datev')->fresh();

        $this->assertSame(BillingMode::Datev, app(BillingModeResolver::class)->effectiveFor($customer));
    }

    public function test_customer_override_workdiary_wins_over_external_org_setting(): void {
        $this->organization->update(['settings' => ['billing_mode' => 'datev']]);
        $customer = $this->makeCustomer('workdiary')->fresh();

        $this->assertSame(BillingMode::Workdiary, app(BillingModeResolver::class)->effectiveFor($customer));
    }

    public function test_invalid_org_setting_falls_back_to_workdiary(): void {
        $this->organization->update(['settings' => ['billing_mode' => 'nonsense']]);
        $customer = $this->makeCustomer()->fresh();

        $this->assertSame(BillingMode::Workdiary, app(BillingModeResolver::class)->effectiveFor($customer));
    }
}
