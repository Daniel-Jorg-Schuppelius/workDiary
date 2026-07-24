<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportBillingExcelCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\Billing\AccountPaymentSource;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\{ActivityCategory, Customer, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 098: Einmal-Import der Excel-Zeiterfassung. Fixture: Jan 2025
 * (2h Fr à 16,50 + 1h So à 17,50, Zahlung 30, Vormonat 100) + Feb 2025
 * (1,5h Di à 16,50). Prüft Konditions-Sätze, Zahlungs-/Saldo-Kette,
 * Anfangssaldo-Übernahme, Idempotenz und Dry-Run.
 */
class ImportBillingExcelCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private CustomerBillingAgreement $agreement;

    private string $fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'is_default' => true,
        ]);
        $this->agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'workdays_per_week' => 6,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'day_type' => 'weekday',
            'hourly_rate' => 16.50,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'day_type' => 'weekend',
            'hourly_rate' => 17.50,
        ]);
        $this->fixture = base_path('tests/Fixtures/customer-billing-excel.xlsx');
    }

    private function runImport(bool $dryRun = false): void {
        $this->artisan('customer-billing:import-excel', array_filter([
            'customer' => (string) $this->customer->id,
            'file' => $this->fixture,
            '--user' => $this->user->email,
            '--dry-run' => $dryRun,
        ]))->assertExitCode(0);
    }

    public function test_import_creates_entries_payments_and_balance_chain(): void {
        $this->runImport();

        // 3 Zeiteinträge mit Konditions-Sätzen (Grund → Tätigkeitskategorie).
        $entries = TimeEntry::query()->orderBy('started_at')->get();
        $this->assertCount(3, $entries);
        $this->assertSame(['16.50', '17.50', '16.50'], $entries->pluck('hourly_rate')->all());
        $this->assertSame('2025-01-03', $entries[0]->date->toDateString());
        // 10:00 Europe/Berlin (CET) = 09:00 UTC.
        $this->assertSame('09:00', $entries[0]->started_at->format('H:i'));
        $this->assertNotNull($entries[0]->customer_billing_rate_id);
        $this->assertSame(
            ['Tier', 'Tier', 'Bürohilfskraft'],
            $entries->map(fn (TimeEntry $e) => ActivityCategory::query()->findOrFail($e->activity_category_id)->label)->all()
        );

        // Zahlung aus „Abgerechnet" (Monatsultimo) + Anfangssaldo aus „Vormonat".
        $payment = $this->agreement->payments()->firstOrFail();
        $this->assertSame('2025-01-31', $payment->paid_on->toDateString());
        $this->assertSame('30.00', $payment->amount);
        $this->assertTrue($payment->source === AccountPaymentSource::Import);

        $this->agreement->refresh();
        $this->assertSame('100.00', $this->agreement->opening_balance);
        $this->assertSame('2024-12-31', $this->agreement->opening_balance_date->toDateString());

        // Saldo-Kette wie in der Excel: Jan Offen 120,50 → Feb Offen 145,25.
        $jan = $this->agreement->statements()->where('year', 2025)->where('month', 1)->firstOrFail();
        $this->assertSame('100.00', $jan->carry_in);
        $this->assertSame('50.50', $jan->gross_value);
        $this->assertSame('30.00', $jan->payments_total);
        $this->assertSame('120.50', $jan->balance);

        $feb = $this->agreement->statements()->where('year', 2025)->where('month', 2)->firstOrFail();
        $this->assertSame('120.50', $feb->carry_in);
        $this->assertSame('24.75', $feb->gross_value);
        $this->assertSame('145.25', $feb->balance);
    }

    public function test_second_run_is_idempotent(): void {
        $this->runImport();
        $this->runImport();

        $this->assertSame(3, TimeEntry::query()->count());
        $this->assertSame(1, $this->agreement->payments()->count());
        $jan = $this->agreement->statements()->where('year', 2025)->where('month', 1)->firstOrFail();
        $this->assertSame('120.50', $jan->balance);
    }

    public function test_dry_run_persists_nothing(): void {
        $this->runImport(dryRun: true);

        $this->assertSame(0, TimeEntry::query()->count());
        $this->assertSame(0, $this->agreement->payments()->count());
        $this->assertSame(0, $this->agreement->statements()->count());
        $this->assertSame('0.00', $this->agreement->refresh()->opening_balance);
    }

    public function test_import_requires_agreement_rates(): void {
        $this->agreement->rates()->delete();

        $this->artisan('customer-billing:import-excel', [
            'customer' => (string) $this->customer->id,
            'file' => $this->fixture,
            '--user' => $this->user->email,
        ])->assertExitCode(1);

        $this->assertSame(0, TimeEntry::query()->count());
    }
}
