<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Customers;

use App\Enums\Billing\{AccountPaymentSource, BillingAgreementMode};
use App\Models\ActivityCategory;
use App\Models\Billing\{CustomerAccountPayment, CustomerBillingAgreement, CustomerBillingRate};
use App\Models\{Customer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 098: Admin-Flows an der Kundenakte — Profil speichern (inkl.
 * Satzzeilen mit Sqid-Kategorien), Zahlung buchen/stornieren, Panel sichtbar.
 */
class BillingAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_agreement_can_be_created_with_rates(): void {
        $category = ActivityCategory::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->post(route('customers.billing.agreement.save', $this->customer), [
            'mode' => 'account',
            'currency' => 'EUR',
            'expected_monthly_amount' => '550',
            'workdays_per_week' => 6,
            'opening_balance' => '2852.37',
            'opening_balance_date' => '2024-12-31',
            'active' => '1',
            'rate_activity_category_id' => ['', $category->sqid, ''],
            'rate_day_type' => ['weekday', 'weekday', 'weekend'],
            'rate_hourly_rate' => ['16.50', '20.00', '17.50'],
        ]);

        $response->assertRedirect(route('customers.show', $this->customer));

        $agreement = $this->customer->billingAgreement()->firstOrFail();
        $this->assertTrue($agreement->mode === BillingAgreementMode::Account);
        $this->assertSame('2852.37', $agreement->opening_balance?->getAmount());
        $this->assertSame(3, $agreement->rates()->count());
        $this->assertSame(
            $category->id,
            $agreement->rates()->where('day_type', 'weekday')->where('hourly_rate', 20.00)->firstOrFail()->activity_category_id
        );
    }

    public function test_saving_replaces_rate_rows(): void {
        $agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $agreement->id,
            'hourly_rate' => 10.00,
        ]);

        $this->actingAs($this->admin)->post(route('customers.billing.agreement.save', $this->customer), [
            'mode' => 'account',
            'currency' => 'EUR',
            'workdays_per_week' => 5,
            'active' => '1',
            'rate_activity_category_id' => [''],
            'rate_day_type' => ['weekday'],
            'rate_hourly_rate' => ['22.00'],
        ])->assertRedirect();

        $agreement->refresh();
        $this->assertSame(5, $agreement->workdays_per_week);
        $this->assertSame(1, $agreement->rates()->count());
        $this->assertSame('22.00', $agreement->rates()->firstOrFail()->hourly_rate?->getAmount());
    }

    public function test_rate_change_via_dialog_revalues_existing_entries(): void {
        // Regression: der Dialog ersetzte die Satzzeilen früher komplett. Die
        // FK time_entries.customer_billing_rate_id ist nullOnDelete — der
        // Konditionsnachweis verschwand und reapplyRates erkannte den Eintrag
        // nicht mehr, die Satzänderung blieb also wirkungslos.
        $agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $rate = CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $agreement->id,
            'day_type' => 'weekday',
            'hourly_rate' => 16.50,
        ]);
        $project = \App\Models\Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $entry = \App\Models\TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'project_id' => $project->id,
            'kind' => \App\Enums\TimeEntry\TimeEntryKind::Work->value,
            'billable' => true,
            'started_at' => '2026-07-17 08:00:00',
            'ended_at' => '2026-07-17 10:00:00',
        ]);
        $this->assertSame($rate->id, $entry->fresh()->customer_billing_rate_id);

        $this->actingAs($this->admin)->post(route('customers.billing.agreement.save', $this->customer), [
            'mode' => 'account',
            'currency' => 'EUR',
            'workdays_per_week' => 6,
            'active' => '1',
            'rate_activity_category_id' => [''],
            'rate_day_type' => ['weekday'],
            'rate_hourly_rate' => ['18.50'],
        ])->assertRedirect();

        $entry->refresh();
        $this->assertSame('18.50', $entry->hourly_rate?->getAmount());
        $this->assertSame('37.00', $entry->rate?->getAmount());
        $this->assertSame($rate->id, $entry->customer_billing_rate_id);
        $this->assertSame(1, $agreement->rates()->count());
    }

    public function test_travel_flat_is_saved_with_its_categories(): void {
        $category = ActivityCategory::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->post(route('customers.billing.agreement.save', $this->customer), [
            'mode' => 'account',
            'currency' => 'EUR',
            'workdays_per_week' => 6,
            'active' => '1',
            'travel_minutes_per_entry' => '20',
            'travel_categories' => [$category->sqid],
            'holidays_as_weekend' => '1',
            'rate_activity_category_id' => [''],
            'rate_day_type' => ['weekday'],
            'rate_hourly_rate' => ['16.50'],
        ])->assertRedirect();

        $agreement = $this->customer->billingAgreement()->firstOrFail();
        $this->assertSame(20, $agreement->travel_minutes_per_entry);
        $this->assertSame([$category->id], $agreement->travel_categories);
        $this->assertTrue($agreement->holidays_as_weekend);
    }

    public function test_statement_detail_lists_the_entries_with_travel(): void {
        $agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'travel_minutes_per_entry' => 20,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $agreement->id,
            'day_type' => 'weekday',
            'hourly_rate' => 18.00,
        ]);
        $project = \App\Models\Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        \App\Models\TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'project_id' => $project->id,
            'kind' => \App\Enums\TimeEntry\TimeEntryKind::Work->value,
            'billable' => true,
            'started_at' => '2026-07-17 08:00:00',
            'ended_at' => '2026-07-17 10:00:00',
        ]);
        $statement = app(\App\Services\Billing\CustomerAccountStatementService::class)
            ->ensure($agreement, 2026, 7);

        $response = $this->actingAs($this->admin)
            ->get(route('customers.billing.statements.show', [$this->customer, $statement]));

        $response->assertOk();
        $response->assertSee(__('customer-billing.travel'));
        $response->assertSee('2:00');   // Arbeitszeit
        $response->assertSee('0:20');   // Anfahrt
        $response->assertSee('42,00 €'); // 140 Min. à 18,00 €
    }

    public function test_payment_can_be_booked_and_voided(): void {
        CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAs($this->admin)->post(route('customers.billing.payments.store', $this->customer), [
            'paid_on' => '2026-07-01',
            'amount' => '550',
            'note' => 'Monatsabschlag',
        ])->assertRedirect();

        $payment = CustomerAccountPayment::query()->firstOrFail();
        $this->assertTrue($payment->source === AccountPaymentSource::Manual);
        $this->assertSame('550.00', $payment->amount?->getAmount());

        $this->actingAs($this->admin)
            ->delete(route('customers.billing.payments.destroy', [$this->customer, $payment]))
            ->assertRedirect();

        $this->assertSoftDeleted('customer_account_payments', ['id' => $payment->id]);
    }

    public function test_non_admin_cannot_save_agreement(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($member)->post(route('customers.billing.agreement.save', $this->customer), [
            'mode' => 'account',
            'currency' => 'EUR',
            'workdays_per_week' => 6,
        ])->assertForbidden();
    }

    public function test_customer_show_renders_billing_panel(): void {
        CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee(__('customer-billing.panel_title'));
    }

    public function test_dialog_fragments_render(): void {
        $this->actingAs($this->admin)
            ->get(route('customers.billing.agreement.edit', $this->customer))
            ->assertOk();

        CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('customers.billing.payments.create', $this->customer))
            ->assertOk();
    }
}
