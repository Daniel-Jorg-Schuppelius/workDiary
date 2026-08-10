<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalVisibilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\User\Permission as P;
use App\Models\{AuditLog, Customer, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-511: sichtbare Portalbereiche und Zeit-Detailstufen pro Kunde.
 * Default-Deny, zentrale Freigabeentscheidung, kundensichere 404 auf
 * nicht freigegebenen Routen, Veröffentlichung einzelner Zeiten.
 */
class CustomerPortalVisibilityTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    private User $portalUser;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Sichtbarkeits-Kunde',
        ]);
        $this->portalUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Portal-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->portalUser->id,
        ]);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** @param array<int, string> $capabilities */
    private function setVisibility(array $capabilities, string $timeDetail = 'none', string $timeScope = 'published', bool $enabled = true): void {
        $this->customer->forceFill(['portal_settings' => [
            'enabled' => $enabled,
            'capabilities' => $capabilities,
            'time_detail' => $timeDetail,
            'time_scope' => $timeScope,
        ]])->save();
    }

    private function makeEntry(array $overrides = []): TimeEntry {
        return TimeEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->portalUser->id,
            'date' => '2026-07-15',
            'minutes' => 90,
            'description' => 'Interne Wartungsnotiz',
        ], $overrides));
    }

    public function test_without_configuration_everything_is_denied(): void {
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee(__('Für Ihren Zugang sind noch keine Bereiche freigegeben.'));

        foreach (['customer.diary.index', 'customer.time-entries.index', 'customer.invoices.index', 'customer.documents.index', 'customer.assets.index', 'customer.tickets.index', 'customer.claims.index', 'customer.rentals.index', 'customer.open-issues.index'] as $routeName) {
            $this->get(route($routeName))->assertNotFound();
        }
    }

    public function test_released_area_is_reachable_others_stay_404(): void {
        $this->setVisibility(['diary']);
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.diary.index'))->assertOk();
        $this->get(route('customer.tickets.index'))->assertNotFound();
        $this->get(route('customer.documents.index'))->assertNotFound();

        // Navigation zeigt nur den freigegebenen Bereich.
        $this->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee(__('Auftragsbuch'))
            ->assertDontSee(route('customer.tickets.index'));
    }

    public function test_global_switch_off_denies_released_areas(): void {
        $this->setVisibility(['diary'], enabled: false);
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.diary.index'))->assertNotFound();
    }

    public function test_time_detail_none_hides_time_route(): void {
        $this->setVisibility(['time_entries'], 'none');
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.time-entries.index'))->assertNotFound();
    }

    public function test_time_summary_shows_totals_without_entries(): void {
        $this->makeEntry(['customer_visible_at' => now()]);
        $this->setVisibility(['time_entries'], 'summary');
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.time-entries.index'))
            ->assertOk()
            ->assertSee('Portal-Projekt')
            ->assertSee(\App\Support\Formats::duration(90, 'clock'))
            ->assertDontSee('Interne Wartungsnotiz');
    }

    public function test_entries_level_hides_description_column(): void {
        $this->makeEntry(['customer_visible_at' => now()]);
        $this->setVisibility(['time_entries'], 'entries');
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.time-entries.index'))
            ->assertOk()
            ->assertSee('Portal-Projekt')
            ->assertDontSee('Interne Wartungsnotiz');
    }

    public function test_description_only_for_published_entries(): void {
        $this->makeEntry(['customer_visible_at' => now(), 'description' => 'Veröffentlichte Beschreibung']);
        $this->setVisibility(['time_entries'], 'entries_with_description');
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.time-entries.index'))
            ->assertOk()
            ->assertSee('Veröffentlichte Beschreibung');
    }

    public function test_published_scope_hides_unpublished_entries(): void {
        $this->makeEntry(['customer_visible_at' => now(), 'description' => 'Sichtbarer Eintrag']);
        $this->makeEntry(['date' => '2026-07-16', 'description' => 'Unveröffentlichter Eintrag']);

        $this->setVisibility(['time_entries'], 'entries_with_description');
        $this->actingAs($this->portalUser, 'customer');
        $this->get(route('customer.time-entries.index'))
            ->assertOk()
            ->assertSee('Sichtbarer Eintrag')
            ->assertDontSee('Unveröffentlichter Eintrag');

        // Kompatibilitäts-Scope „alle": Eintrag erscheint, Beschreibung bleibt
        // unveröffentlichten Einträgen trotzdem vorenthalten.
        $this->setVisibility(['time_entries'], 'entries_with_description', 'all');
        $this->get(route('customer.time-entries.index'))
            ->assertOk()
            ->assertSee('Sichtbarer Eintrag')
            ->assertDontSee('Unveröffentlichter Eintrag');
    }

    public function test_config_update_requires_permission_and_audits(): void {
        $plain = $this->orgUser();
        $this->actingAs($plain)
            ->put(route('customers.portal-visibility.update', $this->customer), [
                'enabled' => '1',
                'capabilities' => ['diary'],
                'time_detail' => 'none',
                'time_scope' => 'published',
            ])
            ->assertForbidden();

        $manager = $this->orgUser();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        foreach ([P::CustomerPortalVisibilityManage, P::CustomerView] as $p) {
            SpatiePermission::findOrCreate($p->value, 'web');
            $manager->givePermissionTo($p->value);
        }

        $this->actingAs($manager)
            ->put(route('customers.portal-visibility.update', $this->customer), [
                'enabled' => '1',
                'capabilities' => ['diary', 'time_entries'],
                'time_detail' => 'entries',
                'time_scope' => 'published',
            ])
            ->assertRedirect();

        $settings = $this->customer->fresh()->portal_settings;
        $this->assertTrue((bool) $settings['enabled']);
        $this->assertSame(['diary', 'time_entries'], $settings['capabilities']);
        $this->assertSame('entries', $settings['time_detail']);

        $audit = AuditLog::query()->where('event', 'portal.visibility.updated')->first();
        $this->assertNotNull($audit, 'Konfigurationsänderungen werden auditiert.');
        $this->assertSame($manager->id, (int) $audit->getAttribute('changes')['by']);

        // Wirkung sofort: bestehende Portal-Session sieht den neuen Stand.
        $this->actingAs($this->portalUser, 'customer');
        $this->get(route('customer.diary.index'))->assertOk();
        $this->get(route('customer.tickets.index'))->assertNotFound();
    }

    public function test_bulk_publish_and_retract_time_entries(): void {
        $a = $this->makeEntry();
        $b = $this->makeEntry(['date' => '2026-07-16']);

        $manager = $this->orgUser();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        SpatiePermission::findOrCreate(P::CustomerPortalVisibilityManage->value, 'web');
        $manager->givePermissionTo(P::CustomerPortalVisibilityManage->value);

        $this->actingAs($manager)
            ->post(route('projects.time-entries.portal-visibility', $this->project), [
                'ids' => [$a->sqid, $b->sqid],
                'mode' => 'publish',
            ])
            ->assertRedirect();
        $this->assertNotNull($a->fresh()->customer_visible_at);
        $this->assertNotNull($b->fresh()->customer_visible_at);

        $this->actingAs($manager)
            ->post(route('projects.time-entries.portal-visibility', $this->project), [
                'ids' => [$a->sqid],
                'mode' => 'retract',
            ])
            ->assertRedirect();
        $this->assertNull($a->fresh()->customer_visible_at);
        $this->assertNotNull($b->fresh()->customer_visible_at);

        // Ohne Permission: 403, nichts verändert.
        $plain = $this->orgUser();
        $this->actingAs($plain)
            ->post(route('projects.time-entries.portal-visibility', $this->project), [
                'ids' => [$b->sqid],
                'mode' => 'retract',
            ])
            ->assertForbidden();
        $this->assertNotNull($b->fresh()->customer_visible_at);
    }

    public function test_internal_rates_never_appear_in_portal_time_view(): void {
        $this->makeEntry(['customer_visible_at' => now(), 'hourly_rate' => '123.45']);
        $this->setVisibility(['time_entries'], 'entries_with_description');
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.time-entries.index'))
            ->assertOk()
            ->assertDontSee('123,45')
            ->assertDontSee('123.45');
    }
}
