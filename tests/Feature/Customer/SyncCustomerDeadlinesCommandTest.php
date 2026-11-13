<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncCustomerDeadlinesCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\Customer\{Customer, CustomerDeadline, CustomerDeadlineProfile};
use App\Services\Customer\Deadlines\CustomerDeadlineRuleSets;
use App\Services\Customer\Deadlines\RuleSets\TaxAdvisoryDeadlineRuleSet;
use App\Services\Fields\FieldDocument;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Jahreslauf `customer-deadlines:sync` (Feature 197, MVP-1632): legt die
 * Fristen aus vorhandenen Fassungen an, ist idempotent, lässt Organisationen
 * ohne eingeschalteten Regelsatz aus und beschränkt sich auf `--organization`.
 */
final class SyncCustomerDeadlinesCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-12-01 05:50:00');
        $this->setUpOrganization(['settings' => ['holidays' => ['provider' => 'Germany\\Berlin']]]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function profileFor(Customer $customer): void {
        $schema = app(CustomerDeadlineRuleSets::class)->get($this->organization, TaxAdvisoryDeadlineRuleSet::KEY)?->traitSchema();
        $this->assertNotNull($schema);
        // Fassung direkt angelegt (ohne Dienst): erst der Lauf erzeugt die Fristen.
        CustomerDeadlineProfile::factory()->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'traits' => new FieldDocument($schema, \App\Services\Fields\FieldValues::normalize($schema, ['vat_interval' => 'quarterly', 'wage_tax_interval' => 'none', 'tax_office_state' => 'Germany\\Berlin'])),
            'valid_from' => '2026-11-01',
        ]);
    }

    public function test_the_yearly_run_creates_deadlines_once_and_only_for_enabled_rule_sets(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->profileFor($customer);

        // Regelsatz aus: nichts.
        $this->artisan('customer-deadlines:sync')->assertSuccessful();
        $this->assertSame(0, CustomerDeadline::query()->count());

        app(CustomerDeadlineRuleSets::class)->enable($this->organization, [TaxAdvisoryDeadlineRuleSet::KEY]);
        $this->artisan('customer-deadlines:sync', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('Fristen angelegt')
            ->assertSuccessful();

        // Ab 01.11.2026: Q3 2026 (fällig 10.10.) nicht, Q4 2026 und alle Quartale 2027.
        $keys = CustomerDeadline::query()->orderBy('period_key')->pluck('period_key')->map(static fn ($p): string => (string) $p)->all();
        $this->assertSame(['2026-Q4', '2027-Q1', '2027-Q2', '2027-Q3', '2027-Q4'], $keys);
        $this->assertSame('2027-01-11', CustomerDeadline::query()->where('period_key', '2026-Q4')->sole()->due_on->toDateString());

        $this->artisan('customer-deadlines:sync')->assertSuccessful();
        $this->assertSame(5, CustomerDeadline::query()->count());
    }

    public function test_an_unknown_organization_runs_nothing(): void {
        app(CustomerDeadlineRuleSets::class)->enable($this->organization, [TaxAdvisoryDeadlineRuleSet::KEY]);
        $this->profileFor(Customer::factory()->create(['organization_id' => $this->organization->id]));

        $this->artisan('customer-deadlines:sync', ['--organization' => '999999'])->assertSuccessful();
        $this->assertSame(0, CustomerDeadline::query()->count());
    }
}
