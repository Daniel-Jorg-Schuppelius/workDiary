<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleOverviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Reselling\{ResaleImport, ResaleSubscription};
use App\Services\Reselling\Register\{PeriodPlanner, ResaleOverview};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Überblick als Aufgabenliste, Abo-Liste auf eigener Route und
 * Produkt-Einstufung mit Filter (Feature 185, MVP-1414/1417/1418).
 */
class ResaleOverviewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'quantity' => 1, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '247.20',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_overview_lists_tasks_with_counts_and_provider_state(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH']);
        $this->subscription(['customer_id' => $customer->id]); // zwei fällige offene Perioden (2025, 2026)
        $this->subscription(['company_name' => 'Unbekannt UG', 'starts_on' => '2026-10-01']); // ohne Halter, nicht fällig
        ResaleImport::query()->create([
            'organization_id' => $this->organization->id, 'provider' => 'qualityhosting', 'kind' => ResaleImport::KIND_CONTRACTS,
            'file_name' => '2026-07-20_Export.xlsx', 'status' => 'done', 'rows_total' => 3,
        ])->forceFill(['created_at' => '2026-07-20 08:00:00'])->save();

        $overview = app(ResaleOverview::class)->build($this->organization);
        $byKey = array_column($overview['tasks'], null, 'key');
        $this->assertSame(2, $byKey['open']['count']);
        $this->assertStringContainsString('494,40', (string) $byKey['open']['detail'], '2 × 247,20 € offen');
        $this->assertSame(1, $byKey['unassigned']['count']);
        $this->assertTrue($byKey['unassigned']['manage'], 'Inbox nur für Pfleger');
        $this->assertSame(1, $byKey['stale_imports']['count'], 'Quality-Hosting-Stand ist 46 Tage alt');
        $this->assertArrayNotHasKey('proposed', $byKey, 'keine Vorschläge → keine Aufgabe');
        $this->assertSame(['qualityhosting'], array_map(static fn(array $i): string => $i['provider']->value, $overview['imports']));
        $this->assertSame(46, $overview['imports'][0]['age']);
        $this->assertTrue($overview['imports'][0]['stale']);
        $this->assertNull($overview['last_run']);

        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.index'))
            ->assertOk()
            ->assertSee(__('resale.overview.tasks'))
            ->assertSee(__('resale.overview.task.open'))
            ->assertSee(route('finance.resale.periods.index', ['status' => 'problems']), false)
            ->assertSee(__('resale.overview.task.unassigned'))
            ->assertSee(__('resale.overview.task.stale_imports'))
            ->assertSee(__('resale.overview.state.age', ['days' => 46]))
            ->assertSee(__('resale.overview.state.never'))
            ->assertSee(route('finance.resale.subscriptions'), false)
            ->assertSee(route('finance.resale.products'), false)
            ->assertDontSee(__('resale.filter.all_kinds')); // keine Abo-Liste mehr auf dem Index

        // Leser: dieselbe Seite ohne die Pflege-Aufgaben.
        $this->actingAs($this->userWithRole('buchhaltung'))->get(route('finance.resale.index'))->assertOk();
    }

    /**
     * Ausnahmen statt Bestätigungen (Phase 163): eine fällige Periode wird erst
     * nach der Karenz zur Aufgabe; Aufgaben stehen in vier Gruppen; die
     * Reiterleiste bleibt vollständig (Inhaber 2026-10-27).
     */
    public function test_grace_period_groups_and_four_tabs(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH']);
        $this->subscription(['customer_id' => $customer->id, 'starts_on' => '2026-08-25']); // 10 Tage fällig: in Karenz
        $this->subscription(['customer_id' => $customer->id, 'starts_on' => '2026-08-01', 'label' => 'Exchange Online (Plan 1)']); // 34 Tage: Aufgabe

        $overview = app(ResaleOverview::class)->build($this->organization);
        $byKey = array_column($overview['tasks'], null, 'key');
        $this->assertSame(1, $byKey['open']['count'], 'nur die Periode nach der Karenz');
        $this->assertSame('berechnen', $byKey['open']['group']);
        $this->assertSame(1, $overview['summary']['grace']);
        $this->assertSame(0, $overview['summary']['planned']);
        $this->assertSame(ResaleOverview::GROUPS, $overview['groups']);
        $this->assertStringContainsString((string) ResaleOverview::GRACE_DAYS, (string) $byKey['open']['detail']);

        $page = $this->actingAs($admin)->get(route('finance.resale.index'))->assertOk();
        $page->assertSee(__('resale.overview.group.berechnen'))
            ->assertSee(__('resale.overview.kpi.open'))
            ->assertSee(__('resale.overview.state.grace', ['days' => ResaleOverview::GRACE_DAYS]))
            ->assertSee(__('resale.tab.periods'))->assertSee(__('resale.tab.accounts'))
            ->assertSee(route('finance.resale.accounts.index'), false)
            ->assertSee(route('finance.resale.reconcile.index'), false)
            ->assertSee(route('finance.resale.prices'), false)
            ->assertSee(__('resale.overview.state.proposed')); // Stichprobe im Stand, keine Aufgabe
        $this->assertArrayNotHasKey('proposed', $byKey);
    }

    public function test_subscription_list_lives_on_its_own_route_with_filters(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH']);
        $this->subscription(['customer_id' => $customer->id, 'label' => 'Kundenabo Exchange']);
        $this->subscription(['is_own_holding' => true, 'label' => 'Hausinternes Exchange']);

        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.subscriptions'))
            ->assertOk()
            ->assertSee(__('resale.title.subscriptions'))
            ->assertSee('Kundenabo Exchange')
            ->assertSee('Hausinternes Exchange')
            ->assertSee(route('finance.resale.subscriptions'), false);
        $this->actingAs($admin)->get(route('finance.resale.subscriptions', ['open' => 1]))->assertOk()->assertSee('Kundenabo Exchange')->assertDontSee('Hausinternes Exchange');
        $this->actingAs($admin)->get(route('finance.resale.subscriptions', ['q' => 'Hausintern']))->assertOk()->assertDontSee('Kundenabo Exchange')->assertSee('Hausinternes Exchange');
        $this->actingAs($this->orgUser())->get(route('finance.resale.subscriptions'))->assertForbidden();
    }

    public function test_product_classification_filters_by_scope_and_search(): void {
        $orgId = (int) $this->organization->id;
        Article::factory()->create(['organization_id' => $orgId, 'name' => 'Microsoft 365 Business Premium']);
        Article::factory()->create(['organization_id' => $orgId, 'name' => 'Wartung Serverraum']);

        $admin = $this->orgAdmin();
        // Standard: nur Abo-Produkte (erkannt am Namen) und Artikel mit Abos.
        $this->actingAs($admin)->get(route('finance.resale.products'))->assertOk()
            ->assertSee('Microsoft 365 Business Premium')
            ->assertDontSee('Wartung Serverraum')
            ->assertSee(__('resale.products.filter.subscriptions'));
        $this->actingAs($admin)->get(route('finance.resale.products', ['scope' => 'all']))->assertOk()
            ->assertSee('Microsoft 365 Business Premium')
            ->assertSee('Wartung Serverraum');
        $this->actingAs($admin)->get(route('finance.resale.products', ['scope' => 'all', 'q' => 'wartung']))->assertOk()
            ->assertDontSee('Microsoft 365 Business Premium')
            ->assertSee('Wartung Serverraum');
        $this->actingAs($admin)->get(route('finance.resale.products', ['scope' => 'unclassified']))->assertOk()
            ->assertSee('Microsoft 365 Business Premium', false)
            ->assertSee('Wartung Serverraum');
        // Unbekannter Filterwert fällt auf den Standard zurück.
        $this->actingAs($admin)->get(route('finance.resale.products', ['scope' => 'xyz']))->assertOk()->assertDontSee('Wartung Serverraum');
    }
}
