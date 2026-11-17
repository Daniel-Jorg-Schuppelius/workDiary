<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanOperationsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\ServicePlan;

use App\Dashboard\Widgets\MyPeriodsWidget;
use App\Enums\Delegation\WorkDelegationKind;
use App\Enums\Diary\Status;
use App\Enums\Period\PeriodKind;
use App\Enums\ServicePlan\ServicePlanCellState;
use App\Enums\User\UserRole;
use App\Models\Classification\EntryType;
use App\Models\Customer\{Customer, CustomerDeadlineProfile, CustomerService};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\ServicePlan\{CustomerServiceWaiver, ServiceTemplate};
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Delegation\WorkDelegationService;
use App\Services\Demo\Contracts\DemoSeedContext;
use App\Services\ServicePlan\Demo\ServicePlanDemoBlock;
use App\Services\ServicePlan\Dto\ServiceKey;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Leistungsplan im Betrieb (Feature 199): Perioden des Jahres anlegen
 * (MVP-1639), Leistungsvorlagen der Branchenprofile und des Kundendialogs
 * (MVP-1674), Karte „Meine Perioden“ und Arbeitsvorrat samt Vertretung
 * (MVP-1648), Demo-Block. Erfundene Kunden und Personen.
 */
final class ServicePlanOperationsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $anna;

    private User $ben;

    private EntryType $support;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-05-15 09:00:00');
        $this->setUpOrganization(['settings' => ['holidays' => ['provider' => 'Germany\\Berlin']]]);
        $this->admin = $this->orgAdmin();
        $this->anna = $this->userWithRole(UserRole::Teamleitung->value, attributes: ['name' => 'Anna Beispiel']);
        $this->ben = $this->userWithRole(UserRole::Teamleitung->value, attributes: ['name' => 'Ben Muster']);
        $this->support = EntryType::factory()->create([
            'organization_id' => $this->organization->id,
            'slug' => 'maintenance',
            'label' => 'Monatsbetreuung',
            'requires_customer' => true,
            'requires_period' => [PeriodKind::Month->value],
        ]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function monthly(Customer $customer, string $from = '2026-01-01'): CustomerService {
        return CustomerService::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'entry_type_id' => $this->support->id,
            'period_kind' => PeriodKind::Month,
            'valid_from' => $from,
        ]);
    }

    /** „Perioden des Jahres anlegen“ verkettet die Folgeperioden, lässt „entfällt“ aus und legt nichts doppelt an. */
    public function test_create_periods_of_the_year_chains_follow_ups(): void {
        $client = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ketten Probe']);
        $newcomer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ohne Vorgänger']);
        $this->monthly($client, '2025-12-01');
        $this->monthly($newcomer);
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'assigned_user_id' => $this->anna->id,
            'customer_id' => $client->id,
            'entry_type_id' => $this->support->id,
            'period_key' => '2025-12',
            'status' => Status::Done->value,
            'title' => 'Monatsbetreuung Dezember 2025',
        ]);
        CustomerServiceWaiver::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'entry_type_id' => $this->support->id,
            'period_key' => '2026-08',
        ]);
        $service = (new ServiceKey((int) $this->support->id))->param();

        $this->actingAs($this->anna)->get(route('service-plan.periods.create-dialog', ['year' => 2026, 'dialog' => 1]))
            ->assertOk()
            ->assertSee('Monatsbetreuung');
        $this->actingAs($this->anna)->post(route('service-plan.periods.create'), ['service' => $service . '|month', 'year' => 2026])
            ->assertRedirect()
            ->assertSessionHas('success');

        $periods = DiaryEntry::query()->where('customer_id', $client->id)->where('period_key', 'like', '2026-%')->pluck('period_key')->map(static fn ($p): string => (string) $p?->key())->sort()->values()->all();
        $this->assertSame(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-09', '2026-10', '2026-11', '2026-12'], $periods, 'August entfällt; die Reihe geht mit dem Juli-Auftrag weiter.');
        $this->assertSame((int) $this->anna->id, (int) DiaryEntry::query()->where('customer_id', $client->id)->where('period_key', '2026-09')->value('assigned_user_id'));
        $this->assertSame(0, DiaryEntry::query()->where('customer_id', $newcomer->id)->count());

        $this->actingAs($this->anna)->post(route('service-plan.periods.create'), ['service' => $service . '|month', 'year' => 2026]);
        $this->assertSame(11, DiaryEntry::query()->where('customer_id', $client->id)->where('period_key', 'like', '2026-%')->count(), 'Zweiter Lauf legt nichts doppelt an.');

        $worker = $this->userWithRole(UserRole::User->value);
        $this->actingAs($worker)->post(route('service-plan.periods.create'), ['service' => $service . '|month', 'year' => 2026])->assertForbidden();
    }

    /** MVP-1674: Branchenprofile liefern Leistungsvorlagen, die Installation ergänzt nur Fehlendes. */
    public function test_branch_profiles_install_service_templates_only_once(): void {
        $installer = app(BranchProfileInstaller::class);
        $result = $installer->install($this->organization, 'steuerberater', $this->admin);
        $this->assertSame(9, $result['created']['service_catalog'] ?? null);
        $income = ServiceTemplate::query()->where('product_group_code', 'einkommensteuer')->sole();
        $this->assertSame(PeriodKind::Year, $income->period_kind);
        $this->assertSame('steuererklaerung', $income->entryType?->slug);
        $this->assertSame('Income tax return', $income->displayLabel('en'));
        $this->assertSame(PeriodKind::Month, ServiceTemplate::query()->whereHas('entryType', static fn ($q) => $q->where('slug', 'fibu'))->sole()->period_kind);

        $income->update(['label' => 'Eigene Bezeichnung']);
        $again = $installer->install($this->organization, 'steuerberater', $this->admin);
        $this->assertSame(0, $again['created']['service_catalog'] ?? null);
        $this->assertSame('Eigene Bezeichnung', $income->fresh()?->label, 'Bestand wird nie still geändert.');

        $installer->install($this->organization, 'pflege', $this->admin);
        $this->assertTrue(ServiceTemplate::query()->whereHas('entryType', static fn ($q) => $q->where('slug', 'pflegevisite'))->where('period_kind', PeriodKind::HalfYear->value)->exists());
        $installer->install($this->organization, 'gebaeudereinigung', $this->admin);
        $this->assertTrue(ServiceTemplate::query()->whereHas('entryType', static fn ($q) => $q->where('slug', 'qualitaetskontrolle'))->exists());
        $installer->install($this->organization, 'it', $this->admin);
        // `maintenance` gab es schon (Eintragsart des Tests) — die Vorlage hängt an ihr.
        $this->assertSame((int) $this->support->id, (int) ServiceTemplate::query()->where('label', 'Monatsbetreuung')->value('entry_type_id'));
    }

    /** Der Dialog „Kunde anlegen“ schlägt die Vorlagen vor; angekreuzte werden Leistungsumfang. */
    public function test_customer_dialog_offers_templates_as_scope_of_services(): void {
        $template = ServiceTemplate::factory()->create([
            'organization_id' => $this->organization->id,
            'entry_type_id' => $this->support->id,
            'period_kind' => PeriodKind::Month,
            'label' => 'Monatsbetreuung',
        ]);

        $this->actingAs($this->admin)->get(route('customers.create'))->assertOk()->assertSee(__('service_plan.templates.legend'))->assertSee($template->sqid);
        $this->actingAs($this->admin)->post(route('customers.store'), ['name' => 'Neukundin Probe', 'service_templates' => [$template->sqid]])->assertRedirect();

        $customer = Customer::query()->where('name', 'Neukundin Probe')->sole();
        $service = CustomerService::query()->where('customer_id', $customer->id)->sole();
        $this->assertSame(PeriodKind::Month, $service->period_kind);
        $this->assertSame('2026-05-01', $service->valid_from->toDateString(), 'ab der laufenden Periode');
    }

    /** Karte „Meine Perioden“ und Arbeitsvorrat „Überfällige Perioden“, auch für die Vertretung. */
    public function test_my_periods_and_overdue_work_items_with_delegation(): void {
        $client = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Vorrat Probe']);
        $this->monthly($client);
        CustomerDeadlineProfile::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'responsible_user_id' => $this->anna->id,
            'valid_from' => '2026-01-01',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $widget = app(MyPeriodsWidget::class);
        $this->assertTrue($widget->availableFor($this->anna));
        $view = $widget->render($this->anna);
        $html = $view instanceof \Illuminate\Contracts\View\View ? $view->render() : $view;
        $this->assertStringContainsString('Vorrat Probe', $html);
        $this->assertStringContainsString(__('service_plan.widget.title'), $html);

        $items = $this->queue($this->anna);
        $this->assertCount(4, $items, 'Januar bis April fehlen und sind überfällig; Mai läuft noch.');
        $this->assertNull($items[0]->onBehalfOf);

        $before = $this->queue($this->ben);
        $this->assertSame([], $before);
        app(WorkDelegationService::class)->create($this->anna, $this->ben, CarbonImmutable::today(), null, WorkDelegationKind::Vacation, null, null, $this->anna);
        $delegated = $this->queue($this->ben);
        $this->assertCount(4, $delegated);
        $this->assertSame('Anna Beispiel', $delegated[0]->onBehalfOf);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->assertTrue($widget->availableFor($this->ben), 'Die Vertretung sieht die Perioden der vertretenen Person auf der Karte.');
    }

    public function test_demo_block_seeds_and_purges(): void {
        $block = app(ServicePlanDemoBlock::class);
        $customers = Customer::factory()->count(3)->sequence(['name' => 'Demo Eins'], ['name' => 'Demo Zwei'], ['name' => 'Demo Drei'])->create(['organization_id' => $this->organization->id]);
        $context = new DemoSeedContext($this->organization, $this->admin, new Collection([$this->admin, $this->anna]), [], null, null, null, $customers->first());
        $this->assertTrue($block->supports($context));
        $counts = $block->seed($context);
        $this->assertSame(['service_templates' => 2, 'customer_services' => 5, 'service_plan_orders' => 5, 'customer_service_waivers' => 1], $counts);
        $this->assertSame(1, CustomerServiceWaiver::query()->count());
        $this->actingAs($this->anna)->get(route('service-plan.index'))->assertOk()->assertSee('Demo Eins')->assertSee(ServicePlanCellState::Review->label());
        $this->actingAs($this->anna)->get(route('service-plan.periods.index'))->assertOk()->assertSee('Demo Zwei');

        $block->purge($this->organization);
        $this->assertSame(3, Customer::query()->whereIn('name', ['Demo Eins', 'Demo Zwei', 'Demo Drei'])->count(), 'Die Demo-Kunden gehören dem Grundbestand.');
        $this->assertSame(0, CustomerService::query()->count());
        $this->assertSame(0, CustomerServiceWaiver::query()->count());
        $this->assertSame(0, ServiceTemplate::query()->count());
        $this->assertSame(0, EntryType::query()->whereIn('slug', ServicePlanDemoBlock::ENTRY_TYPES)->count());
    }

    /** @return list<WorkItem> */
    private function queue(User $viewer): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $queue = app(WorkQueue::class);
        $queue->forget($viewer, $this->organization);

        return array_values(array_filter($queue->itemsFor($viewer, $this->organization), static fn (WorkItem $item): bool => $item->source === 'service_plan_period'));
    }
}
