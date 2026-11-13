<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineProfileInstallTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\Contract\{ContractKind, ContractObligationKind, ContractObligationStatus};
use App\Enums\Demo\DemoIndustry;
use App\Models\Contract\{Contract, ContractObligation, ContractTemplate};
use App\Models\Customer\{Customer, CustomerDeadline};
use App\Models\Platform\User;
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Contract\WorkQueue\CounsellingVisitObligationNoticeSource;
use App\Services\Customer\Deadlines\CustomerDeadlineRuleSets;
use App\Services\Customer\Demo\CustomerDeadlineDemoBlock;
use App\Services\Demo\Contracts\DemoSeedContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Branchenprofile und Bestand (Feature 197, MVP-1630/1673): `deadline_rule_sets`
 * schaltet Regelsätze nur ein, das Pflegeprofil v8 legt den Beratungsbesuch
 * nicht mehr als Vertragspflicht an, vorhandene Pflichten bleiben und der
 * Arbeitsvorrat weist auf sie hin; der Demo-Block nutzt erfundene Kunden.
 */
final class CustomerDeadlineProfileInstallTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-03-02 09:00:00');
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_tax_profile_enables_its_rule_set_and_only_adds(): void {
        $ruleSets = app(CustomerDeadlineRuleSets::class);
        $ruleSets->setEnabled($this->organization, ['home_care']);

        $result = app(BranchProfileInstaller::class)->install($this->organization->refresh(), 'steuerberater', $this->admin);

        // v5: Leistungsvorlagen des Leistungsplans (Feature 199, MVP-1674); v6: Prüfpaket GwG (Feature 200, MVP-1642).
        $this->assertSame(6, $result['version']);
        $this->assertSame(1, $result['created']['deadline_rule_sets'] ?? null);
        $this->assertSame(['home_care', 'tax_advisory'], $ruleSets->enabledKeys($this->organization->refresh()));

        $again = app(BranchProfileInstaller::class)->install($this->organization->refresh(), 'steuerberater', $this->admin, force: true);
        $this->assertSame(0, $again['created']['deadline_rule_sets'] ?? 0);
    }

    public function test_the_care_profile_drops_the_quarterly_obligation_and_hints_at_the_old_ones(): void {
        $result = app(BranchProfileInstaller::class)->install($this->organization, 'pflege', $this->admin);

        // v9: Pflegevisite und Leistungsvorlagen (Feature 199, MVP-1674); v10: Prüfpaket Pflege-Erstaufnahme (Feature 200, MVP-1675).
        $this->assertSame(10, $result['version']);
        $this->assertContains('home_care', app(CustomerDeadlineRuleSets::class)->enabledKeys($this->organization->refresh()));
        $template = ContractTemplate::query()->where('organization_id', $this->organization->id)->where('name', 'like', 'Pflegevertrag%')->sole();
        $titles = array_column((array) $template->obligations, 'title');
        $this->assertNotContains(CounsellingVisitObligationNoticeSource::LEGACY_TITLE, $titles);
        $this->assertContains('Pflegevisite', $titles);

        // Bestand aus Profilversion 7: eine offene Pflicht „alle drei Monate“ — bleibt stehen, der Arbeitsvorrat weist darauf hin.
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klient Bestand']);
        $contract = Contract::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'kind' => ContractKind::Service]);
        $legacy = ContractObligation::query()->create([
            'organization_id' => $this->organization->id, 'contract_id' => $contract->id, 'kind' => ContractObligationKind::Documentation,
            'title' => CounsellingVisitObligationNoticeSource::LEGACY_TITLE, 'due_on' => '2026-04-01', 'warn_days_before' => 21,
            'recurring' => true, 'recurrence_months' => 3, 'status' => ContractObligationStatus::Open,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $source = app(CounsellingVisitObligationNoticeSource::class);
        $this->assertTrue($source->available($this->admin));
        $items = iterator_to_array($source->items($this->admin, $this->organization->refresh()), false);
        $this->assertCount(1, $items);
        $this->assertSame(1, $items[0]->count);
        $this->assertSame(ContractObligationStatus::Open, $legacy->refresh()->status, 'der Bestand wird nie still geändert');
    }

    public function test_the_demo_block_seeds_and_purges_invented_customers(): void {
        $block = app(CustomerDeadlineDemoBlock::class);
        $context = new DemoSeedContext($this->organization, $this->admin, collect([$this->admin]), [], DemoIndustry::Steuerberater, null, null);

        $this->assertTrue($block->supports($context));
        $counts = $block->seed($context);

        $this->assertSame(2, $counts['customer_deadline_profiles']);
        $this->assertGreaterThan(0, $counts['customer_deadlines']);
        $this->assertSame(1, CustomerDeadline::query()->where('status', 'completed')->count());

        $block->purge($this->organization);
        $this->assertSame(0, Customer::query()->whereIn('name', CustomerDeadlineDemoBlock::CUSTOMERS)->count());
        $this->assertSame(0, CustomerDeadline::query()->count());
    }
}
