<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Customers;

use App\Enums\Billing\BillingAgreementMode;
use App\Enums\Finance\BillingMode;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\{Customer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 098 (Retainer-Modus): Admin-Validierung (Retainer ⇒ Lexoffice-
 * Hoheit + Pauschalbetrag) und Panel-Anzeige.
 */
class RetainerAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function customer(bool $lexoffice): Customer {
        return Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'billing_mode' => $lexoffice ? BillingMode::Lexoffice->value : BillingMode::Workdiary->value,
        ]);
    }

    public function test_retainer_requires_lexoffice_authority(): void {
        $customer = $this->customer(lexoffice: false);

        $this->actingAs($this->admin)->from(route('customers.show', $customer))
            ->post(route('customers.billing.agreement.save', $customer), [
                'mode' => 'retainer',
                'currency' => 'EUR',
                'workdays_per_week' => 6,
                'expected_monthly_amount' => '550',
                'active' => '1',
            ])
            ->assertSessionHasErrors('mode');

        $this->assertNull($customer->billingAgreement()->first());
    }

    public function test_retainer_requires_monthly_amount(): void {
        $customer = $this->customer(lexoffice: true);

        $this->actingAs($this->admin)->from(route('customers.show', $customer))
            ->post(route('customers.billing.agreement.save', $customer), [
                'mode' => 'retainer',
                'currency' => 'EUR',
                'workdays_per_week' => 6,
                'expected_monthly_amount' => '0',
                'active' => '1',
            ])
            ->assertSessionHasErrors('expected_monthly_amount');
    }

    public function test_retainer_agreement_saves_with_lexoffice(): void {
        $customer = $this->customer(lexoffice: true);

        $this->actingAs($this->admin)
            ->post(route('customers.billing.agreement.save', $customer), [
                'mode' => 'retainer',
                'currency' => 'EUR',
                'workdays_per_week' => 6,
                'expected_monthly_amount' => '550',
                'active' => '1',
                'rate_activity_category_id' => [''],
                'rate_day_type' => ['weekday'],
                'rate_hourly_rate' => ['16.50'],
            ])
            ->assertRedirect(route('customers.show', $customer));

        $agreement = $customer->billingAgreement()->firstOrFail();
        $this->assertTrue($agreement->mode === BillingAgreementMode::Retainer);
        $this->assertSame('550.00', $agreement->expected_monthly_amount);
    }

    public function test_panel_shows_retainer_block(): void {
        $customer = $this->customer(lexoffice: true);
        $agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'mode' => 'retainer',
            'expected_monthly_amount' => 550.00,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $agreement->id,
            'hourly_rate' => 16.50,
        ]);

        $this->actingAs($this->admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(__('customer-billing.trueup'))
            ->assertSee(__('customer-billing.retainer_invoice'));
    }
}
