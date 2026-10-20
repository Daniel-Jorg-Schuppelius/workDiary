<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalTimeEntriesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\Project\ProjectStatus;
use App\Enums\User\Permission as P;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Phase 154 (MVP-1237/1238): Jahresgrenze der Portalzeiten (Organisation,
 * Kunde, unbegrenzt), Jahres-Tabs, Filter nur aus der Grundmenge,
 * Sortierung, Kennzahlen gleich der Liste, Dashboard aus derselben Grundmenge.
 */
class PortalTimeEntriesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    private User $portalUser;

    private User $worker;

    private Project $project;

    private Project $otherProject;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        // Fester Stichtag: Jahresgrenzen hängen am laufenden Kalenderjahr.
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Zeiten-Kunde']);
        $this->portalUser = User::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $this->worker = User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Werner Werker']);
        $this->project = $this->makeProject('Portal-Projekt');
        $this->otherProject = $this->makeProject('Zweites Projekt');
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function makeProject(string $name, ?Customer $customer = null): Project {
        return Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => ($customer ?? $this->customer)->id,
            'name' => $name,
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->portalUser->id,
        ]);
    }

    /** @param array<string, mixed> $settings */
    private function setVisibility(string $timeDetail = 'entries', array $settings = []): void {
        $this->customer->forceFill(['portal_settings' => $settings + [
            'enabled' => true,
            'capabilities' => ['time_entries'],
            'time_detail' => $timeDetail,
            'time_scope' => 'published',
        ]])->save();
        // Das Portalkonto cacht die Kundenrelation über Requests hinweg — neu laden.
        $this->portalUser->unsetRelation('customer');
    }

    /** @param array<string, mixed> $overrides */
    private function entry(string $date, int $minutes, array $overrides = []): TimeEntry {
        return TimeEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->worker->id,
            'date' => $date,
            'minutes' => $minutes,
            'description' => 'Eintrag ' . $date,
            'customer_visible_at' => now(),
        ], $overrides));
    }

    private function yearUrl(int $year): string {
        return route('customer.time-entries.index', ['year' => $year]);
    }

    public function test_default_limit_shows_three_calendar_years_including_the_current(): void {
        $this->setVisibility();
        $this->entry('2026-03-01', 60);
        $this->entry('2024-05-01', 60);
        $this->entry('2023-05-01', 60);

        $this->actingAs($this->portalUser, 'customer');
        $page = $this->get(route('customer.time-entries.index'))->assertOk();
        $page->assertSee($this->yearUrl(2024), false)
            ->assertDontSee($this->yearUrl(2023), false);
        $this->assertSame('2024-01-01', $page->viewData('oldestVisibleDate')?->toDateString());
        $this->assertSame([2026, 2024], $page->viewData('years'));
        $this->assertSame(2026, $page->viewData('year'));

        // Ein Jahr außerhalb der Grenze fällt auf das laufende zurück.
        $this->get($this->yearUrl(2023))->assertOk()->assertViewHas('year', 2026);
    }

    public function test_customer_override_and_unlimited_change_the_visible_years(): void {
        $this->setVisibility('entries', ['time_years' => 1]);
        $this->entry('2026-03-01', 60);
        $this->entry('2025-05-01', 60);
        $this->entry('2019-05-01', 60);

        $this->actingAs($this->portalUser, 'customer');
        $this->assertSame([2026], $this->get(route('customer.time-entries.index'))->assertOk()->viewData('years'));

        $this->setVisibility('entries', ['time_years' => 0]);
        $page = $this->get(route('customer.time-entries.index'))->assertOk();
        $this->assertSame([2026, 2025, 2019], $page->viewData('years'));
        $this->assertNull($page->viewData('oldestVisibleDate'));
    }

    public function test_organization_default_applies_without_customer_override(): void {
        $this->organization->forceFill(['settings' => ['customer_portal' => ['time_entry_years' => 5]]])->save();
        $this->setVisibility();
        $this->entry('2026-03-01', 60);
        $this->entry('2022-05-01', 60);
        $this->entry('2021-05-01', 60);

        $this->actingAs($this->portalUser, 'customer');
        $this->assertSame([2026, 2022], $this->get(route('customer.time-entries.index'))->assertOk()->viewData('years'));
    }

    public function test_year_tabs_and_filters_narrow_the_list_and_kpis_match_the_rows(): void {
        $this->setVisibility('entries_with_description');
        $this->entry('2026-02-10', 90, ['description' => 'Februar Portal']);
        $this->entry('2026-05-10', 120, ['description' => 'Mai Portal']);
        $this->entry('2026-05-12', 30, ['project_id' => $this->otherProject->id, 'description' => 'Mai Zweites']);
        $this->entry('2025-05-10', 45, ['description' => 'Vorjahr']);

        $this->actingAs($this->portalUser, 'customer');

        $page = $this->get(route('customer.time-entries.index'))->assertOk();
        $page->assertSee('Februar Portal')->assertSee('Mai Portal')->assertSee('Mai Zweites')->assertDontSee('Vorjahr');
        $this->assertSame(240, $page->viewData('kpis')['year_minutes']);
        $this->assertSame(3, $page->viewData('kpis')['entries']);
        $this->assertSame(2, $page->viewData('kpis')['projects']);

        $this->get($this->yearUrl(2025))->assertOk()->assertSee('Vorjahr')->assertDontSee('Mai Portal');

        $filtered = $this->get(route('customer.time-entries.index', ['year' => 2026, 'project' => $this->otherProject->sqid]))->assertOk();
        $filtered->assertSee('Mai Zweites')->assertDontSee('Mai Portal');
        $this->assertSame(30, $filtered->viewData('kpis')['filtered_minutes']);
        $this->assertSame(240, $filtered->viewData('kpis')['year_minutes']);

        $range = $this->get(route('customer.time-entries.index', ['year' => 2026, 'from' => '2026-05-01', 'to' => '2026-05-31']))->assertOk();
        $range->assertSee('Mai Portal')->assertDontSee('Februar Portal');
        $this->assertSame(150, $range->viewData('kpis')['filtered_minutes']);

        // Jahresfremde Grenzen entfallen still.
        $this->get(route('customer.time-entries.index', ['year' => 2026, 'from' => '2025-05-01', 'to' => '2025-05-31']))
            ->assertOk()->assertSee('Februar Portal');

        $this->get(route('customer.time-entries.index', ['year' => 2026, 'q' => 'Zweites']))
            ->assertOk()->assertSee('Mai Zweites')->assertDontSee('Mai Portal');
    }

    public function test_foreign_project_sqid_is_ignored_and_user_filter_only_in_entry_levels(): void {
        $foreignCustomer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Fremder Kunde']);
        $foreignProject = $this->makeProject('Fremdes Projekt', $foreignCustomer);
        $this->setVisibility();
        $this->entry('2026-02-10', 90);
        $second = User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Zweite Person']);
        $this->entry('2026-02-11', 60, ['user_id' => $second->id]);

        $this->actingAs($this->portalUser, 'customer');

        $page = $this->get(route('customer.time-entries.index', ['project' => $foreignProject->sqid]))->assertOk();
        $this->assertSame(2, $page->viewData('kpis')['entries'], 'Fremde Sqids verschwinden still.');
        $page->assertDontSee('Fremdes Projekt');

        // Das Auswahlfeld nennt beide Personen, die Tabelle nur die gefilterte.
        $byUser = $this->get(route('customer.time-entries.index', ['user' => $second->sqid]))->assertOk();
        $this->assertSame(1, $byUser->viewData('kpis')['entries']);
        $this->assertSame(60, $byUser->viewData('kpis')['filtered_minutes']);
        $this->assertSame($second->sqid, $byUser->viewData('filters')['user']);

        $this->setVisibility('summary');
        $summary = $this->get(route('customer.time-entries.index', ['user' => $second->sqid]))->assertOk();
        $summary->assertDontSee('name="user"', false)->assertDontSee('Zweite Person');
        $this->assertSame(150, $summary->viewData('kpis')['filtered_minutes'], 'Der Mitarbeiterfilter gilt in der Summenstufe nicht.');
    }

    public function test_search_only_hits_published_descriptions(): void {
        $this->setVisibility('entries_with_description', ['time_scope' => 'all']);
        $this->entry('2026-02-10', 90, ['description' => 'Geheimer Hinweis', 'customer_visible_at' => null]);
        $this->entry('2026-02-11', 60, ['description' => 'Offener Hinweis']);

        $this->actingAs($this->portalUser, 'customer');
        $this->get(route('customer.time-entries.index', ['q' => 'Hinweis']))->assertOk()
            ->assertSee('Offener Hinweis')->assertDontSee('Geheimer Hinweis');
    }

    public function test_sorting_uses_the_whitelist_and_falls_back(): void {
        $this->setVisibility();
        $this->entry('2026-02-10', 30);
        $this->entry('2026-02-11', 240);

        $this->actingAs($this->portalUser, 'customer');
        $asc = $this->get(route('customer.time-entries.index', ['sort' => 'minutes', 'dir' => 'asc']))->assertOk()->assertSeeInOrder(['0:30 h', '4:00 h']);
        $this->assertSame('minutes', $asc->viewData('sort'));

        $fallback = $this->get(route('customer.time-entries.index', ['sort' => 'hourly_rate', 'dir' => 'asc']))->assertOk();
        $this->assertSame(['date', 'desc'], [$fallback->viewData('sort'), $fallback->viewData('dir')]);
    }

    public function test_dashboard_uses_the_same_base_set(): void {
        $this->setVisibility();
        $this->entry('2026-03-01', 60);
        $this->entry('2025-03-01', 45);
        $this->entry('2019-03-01', 600);

        $this->actingAs($this->portalUser, 'customer');
        $this->get(route('customer.dashboard'))->assertOk()
            ->assertViewHas('stats', static fn (array $stats): bool => $stats['time_minutes'] === 60 && $stats['time_entries'] === 2);
    }

    public function test_panel_stores_customer_override_and_empty_means_default(): void {
        $manager = $this->orgUser();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        foreach ([P::CustomerPortalVisibilityManage, P::CustomerView] as $permission) {
            SpatiePermission::findOrCreate($permission->value, 'web');
            $manager->givePermissionTo($permission->value);
        }
        $payload = ['enabled' => '1', 'capabilities' => ['time_entries'], 'time_detail' => 'entries', 'time_scope' => 'published'];

        $this->actingAs($manager)
            ->put(route('customers.portal-visibility.update', $this->customer), $payload + ['time_years' => '0'])
            ->assertRedirect();
        $this->assertSame(0, ((array) $this->customer->refresh()->portal_settings)['time_years'] ?? null);

        $this->actingAs($manager)
            ->put(route('customers.portal-visibility.update', $this->customer), $payload + ['time_years' => ''])
            ->assertRedirect();
        $this->assertArrayNotHasKey('time_years', (array) $this->customer->refresh()->portal_settings);

        $this->actingAs($manager)
            ->put(route('customers.portal-visibility.update', $this->customer), $payload + ['time_years' => '99'])
            ->assertSessionHasErrors('time_years');
    }
}
