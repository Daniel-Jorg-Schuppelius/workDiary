<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleConnectionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Platform\User;
use App\Models\Reselling\{ResaleLicenseProduct, ResaleProviderSupplier, ResaleSubscription};
use App\Models\Supplier\Supplier;
use App\Services\Demo\Contracts\DemoSeedContext;
use App\Services\Privacy\SubjectData\CustomerDocumentsSection;
use App\Services\Reselling\Demo\ResaleDemoBlock;
use App\Services\Reselling\EarlyWarnings\ResaleUnbilledWarningSource;
use App\Services\Reselling\Management\ResaleKpiSource;
use App\Services\Reselling\Register\{PeriodPlanner, ResaleOverview, ResaleWorkItemSource};
use App\Services\Reselling\Search\{LicenseProductSearchDomain, ResaleSubscriptionSearchDomain};
use App\Services\Search\Dto\SearchDomainFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Phase 175: Das Register hängt an Arbeitsvorrat, globaler Suche, Leitungssicht,
 * Frühwarnung, Auskunft und Demo — überall mit denselben Zahlen wie der Überblick.
 */
class ResaleConnectionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH']);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** Factories verstellen den Spatie-Team-Kontext; im Request setzt ihn die Middleware. */
    private function signIn(User $user): void {
        $this->actingAs($user);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'external_id' => 'CNLCON00156', 'provider_account' => 'CNL00007', 'company_name' => 'Badhaus Baeder', 'customer_id' => $this->customer->id,
            'quantity' => 1, 'starts_on' => '2025-03-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
            'purchase_unit_price' => '187.92', 'sale_unit_price' => '247.20',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_overview_names_providers_without_a_supplier_until_one_is_mapped(): void {
        $this->subscription();
        $tasks = array_column(app(ResaleOverview::class)->build($this->organization)['tasks'], null, 'key');
        $this->assertSame(1, $tasks['purchase_suppliers']['count'] ?? 0);
        $this->assertStringContainsString(SubscriptionProvider::QualityHosting->label(), (string) $tasks['purchase_suppliers']['detail']);

        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        ResaleProviderSupplier::query()->create(['organization_id' => $this->organization->id, 'supplier_id' => $supplier->id, 'provider' => SubscriptionProvider::QualityHosting]);
        $this->assertArrayNotHasKey('purchase_suppliers', array_column(app(ResaleOverview::class)->build($this->organization)['tasks'], null, 'key'));
    }

    public function test_work_queue_carries_the_overview_tasks_by_permission(): void {
        $this->subscription();
        $this->subscription(['external_id' => 'CNLCON00999', 'customer_id' => null, 'company_name' => 'Unbekannte GmbH']);
        $source = app(ResaleWorkItemSource::class);
        $admin = $this->orgAdmin();
        $this->signIn($admin);
        $this->assertTrue($source->available($admin));
        $items = collect($source->items($admin, $this->organization))->keyBy('key');
        $this->assertSame(4, $items['resale:open']->count, 'zwei Abos mit je zwei fälligen Perioden');
        $this->assertSame(route('finance.resale.reconcile.next'), $items['resale:open']->url);
        $this->assertSame(1, $items['resale:unassigned']->count);
        $this->assertSame(route('finance.resale.index'), $items['resale:purchase_suppliers']->url, 'Dialog-Aufgabe führt in den Überblick');
        $this->assertFalse($items->has('resale:renewals'));
        $this->assertFalse($items->has('resale:plan_findings'));

        $member = $this->orgUser();
        $this->signIn($member);
        $this->assertFalse($source->available($member), 'ohne Leserecht fragt der Arbeitsvorrat nicht');
    }

    public function test_global_search_finds_subscriptions_by_contract_account_company_and_holder(): void {
        $subscription = $this->subscription();
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Systemhaus Nord GmbH']);
        $foreign = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Kanzlei Seewind']);
        $viaPartner = $this->subscription(['external_id' => 'CNLCON00200', 'provider_account' => 'CNL00020', 'company_name' => null, 'customer_id' => null, 'foreign_customer_id' => $foreign->id, 'label' => 'Exchange Online (Plan 1)']);
        $other = \App\Models\Platform\Organization::factory()->create();
        ResaleSubscription::query()->create([
            'organization_id' => $other->id, 'kind' => 'license', 'provider' => 'manual', 'label' => 'Microsoft 365 Business Premium', 'external_id' => 'CNLCON00156',
            'quantity' => 1, 'starts_on' => '2025-03-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ]);
        $domain = app(ResaleSubscriptionSearchDomain::class);
        $admin = $this->orgAdmin();
        $this->signIn($admin);
        $this->assertTrue($domain->available($admin));
        $ids = fn(string $term, SearchDomainFilters $filters = new SearchDomainFilters): array => array_column($domain->search($admin, $this->organization->id, $term, $filters, 10), 'id');

        $this->assertSame([$subscription->id], $ids('CNLCON00156'), 'Anbieter-Kennung, nur die eigene Organisation');
        $this->assertSame([$subscription->id], $ids('CNL00007'));
        $this->assertSame([$subscription->id], $ids('Baeder'));
        $this->assertSame([$viaPartner->id], $ids('Seewind'));
        $this->assertSame([$viaPartner->id], $ids('Online', new SearchDomainFilters(customer: $partner->id)), 'Kundenfilter trifft auch Endkunden des Partners');
        $this->assertSame([], $ids('Microsoft', new SearchDomainFilters(person: $admin->id)));
        $hit = $domain->search($admin, $this->organization->id, 'CNLCON00156', new SearchDomainFilters, 10)[0];
        $this->assertSame(route('finance.resale.show', $subscription->sqid), $hit['url']);
        $this->assertStringContainsString('Badhaus Bäder GmbH', (string) $hit['subtitle']);

        $member = $this->orgUser();
        $this->signIn($member);
        $this->assertFalse($domain->available($member));
    }

    public function test_global_search_finds_licence_products_but_knows_no_customer_filter(): void {
        $product = ResaleLicenseProduct::query()->create(['organization_id' => $this->organization->id, 'name' => 'Windows Server 2025 Standard', 'manufacturer' => 'Microsoft', 'key_roles' => []]);
        $domain = app(LicenseProductSearchDomain::class);
        $admin = $this->orgAdmin();
        $this->signIn($admin);
        $hits = $domain->search($admin, $this->organization->id, 'Server 2025', new SearchDomainFilters, 10);
        $this->assertSame([$product->id], array_column($hits, 'id'));
        $this->assertSame(route('finance.resale.licenses.index', ['product' => $product->sqid]), $hits[0]['url']);
        $this->assertSame([], $domain->search($admin, $this->organization->id, 'Server', new SearchDomainFilters(customer: $this->customer->id), 10));
    }

    public function test_management_view_and_early_warning_use_the_register_numbers(): void {
        $this->subscription(); // Perioden ab 05.03.2025 und 05.03.2026, beide fällig
        $admin = $this->orgAdmin();
        $this->signIn($admin);
        $kpis = app(ResaleKpiSource::class)->kpis($admin, $this->organization, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-09-04'));
        $this->assertCount(2, $kpis);
        $this->assertSame((string) __('reporting.management.kpi.resale_open'), $kpis[0]->label);
        $this->assertSame('2', $kpis[0]->value);
        $this->assertSame('warning', $kpis[0]->tone);
        $this->assertSame((string) __('reporting.management.kpi.resale_margin'), $kpis[1]->label);
        $this->assertSame((string) __('reporting.management.kpi.resale_margin_hint', ['periods' => 1, 'actual' => 0]), $kpis[1]->hint);

        $warnings = app(ResaleUnbilledWarningSource::class)->warnings($this->organization);
        $this->assertCount(1, $warnings);
        $this->assertTrue($warnings[0]->subject->is($this->customer));
        $this->assertSame(2, $warnings[0]->params['count']);
        $this->assertSame(route('finance.resale.reconcile.show', $this->customer), $warnings[0]->url);

        $member = $this->orgUser();
        $this->signIn($member);
        $this->assertSame([], app(ResaleKpiSource::class)->kpis($member, $this->organization, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-09-04')));
    }

    public function test_subject_data_lists_the_subscriptions_of_the_customer_and_of_its_end_customers(): void {
        $this->subscription();
        $foreign = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Kanzlei Seewind']);
        $this->subscription(['external_id' => 'CNLCON00200', 'customer_id' => null, 'foreign_customer_id' => $foreign->id, 'label' => 'Exchange Online (Plan 1)']);
        $stranger = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->subscription(['external_id' => 'CNLCON00300', 'customer_id' => $stranger->id, 'label' => 'Fremdes Abo']);

        $families = array_column(app(CustomerDocumentsSection::class)->build($this->customer)['families'], null, 'table');
        $this->assertSame(2, $families['resale_subscriptions']['count']);
        $labels = array_column($families['resale_subscriptions']['rows'], 1);
        sort($labels);
        $this->assertSame(['Exchange Online (Plan 1)', 'Microsoft 365 Business Premium'], $labels);
    }

    public function test_demo_block_seeds_once_and_purges_only_its_own_rows(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $own = $this->subscription();
        $block = new ResaleDemoBlock;
        $context = new DemoSeedContext($this->organization, $admin, collect([$admin]), [], null, null, null, $this->customer);
        $this->assertTrue($block->supports($context));
        $this->assertSame(['resale_subscriptions' => 4], $block->seed($context));
        $this->assertSame(['resale_subscriptions' => 0], $block->seed($context), 'zweiter Lauf legt nichts doppelt an');
        $this->assertSame(5, ResaleSubscription::query()->count());
        $this->assertGreaterThan(0, ResaleSubscription::query()->where('external_id', 'DEMO-1')->sole()->periods()->count());
        $this->assertFalse($block->supports(new DemoSeedContext($this->organization, $admin, collect([$admin]), [], null, ['module.projects'], null, $this->customer)));

        $block->purge($this->organization);
        $this->assertSame([$own->id], ResaleSubscription::query()->pluck('id')->all());
    }

    public function test_purchase_price_that_no_longer_follows_the_provider_is_adopted_from_the_price_check(): void {
        // Befund Produktion 2026-11-09: Nach einer verpassten Preisänderung blieb der Einkaufspreis für immer beim alten Stand.
        $stuck = $this->subscription(['provider_unit_price' => '202.74']);
        $following = $this->subscription(['external_id' => 'CNLCON00157', 'provider_unit_price' => '187.92']);
        $admin = $this->orgAdmin();
        $this->signIn($admin);
        $tasks = array_column(app(ResaleOverview::class)->build($this->organization)['tasks'], null, 'key');
        $this->assertSame(1, $tasks['provider_prices']['count'] ?? 0);

        $this->actingAs($admin)->get(route('finance.resale.prices'))->assertOk()->assertSee(route('finance.resale.prices.provider.create'), false);
        $this->actingAs($admin)->get(route('finance.resale.prices.provider.create'))->assertOk()->assertSee($stuck->sqid)->assertDontSee($following->sqid);
        $this->actingAs($admin)->post(route('finance.resale.prices.provider.store'), ['ids' => [$stuck->sqid, $following->sqid]])
            ->assertRedirect(route('finance.resale.prices'))->assertSessionHas('success', trans_choice('resale.prices.provider.flash', 1, ['count' => 1]));

        $this->assertSame('202.7400', $stuck->fresh()->purchase_unit_price?->getAmount());
        $this->assertSame('202.74', $stuck->periods()->orderByDesc('starts_on')->firstOrFail()->expected_purchase?->getAmount(), 'offene Periode bekommt das neue Soll');
        $this->assertSame('187.9200', $following->fresh()->purchase_unit_price?->getAmount());
        $this->actingAs($this->orgUser())->get(route('finance.resale.prices.provider.create'))->assertForbidden();
    }
}
