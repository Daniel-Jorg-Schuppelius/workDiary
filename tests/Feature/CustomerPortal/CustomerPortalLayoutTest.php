<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalLayoutTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\CustomerPortal\PortalCapability;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\CustomerPortal\PortalNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Phase 154 (MVP-1239/1240): Navigation aus einer Liste — nur freigegebene
 * Bereiche, aktiver Eintrag, Drawer, Kontomenü, Fuß, Bereichskacheln der
 * Startseite aus derselben Liste; Zeiten nur mit Detailstufe.
 */
class CustomerPortalLayoutTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Layout-Kunde']);
        $this->portalUser = User::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Lara Layout']);
    }

    public function test_navigation_shows_only_released_areas_with_active_item(): void {
        $this->allowPortal($this->customer, ['diary', 'documents']);
        $this->actingAs($this->portalUser, 'customer');

        $page = $this->get(route('customer.diary.index'))->assertOk();
        $page->assertSee(route('customer.diary.index'), false)
            ->assertSee(route('customer.documents.index'), false)
            ->assertDontSee(route('customer.tickets.index'), false)
            ->assertDontSee(route('customer.invoices.index'), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('id="portal-nav-drawer"', false)
            ->assertSee('drawer-side', false)
            ->assertSee('Lara Layout')
            ->assertSee('Layout-Kunde')
            ->assertSee(route('legal.imprint'), false)
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('customer.logout'), false);
    }

    public function test_time_item_needs_a_detail_level_and_insights_its_release(): void {
        $this->allowPortal($this->customer, ['time_entries'], timeDetail: 'none');
        $items = app(PortalNavigation::class)->items($this->customer->refresh());
        $this->assertNotContains('time', array_column($items, 'key'));
        $this->assertNotContains('insights', array_column($items, 'key'));

        $this->allowPortal($this->customer, ['time_entries', 'insights'], timeDetail: 'summary');
        $items = app(PortalNavigation::class)->items($this->customer->refresh());
        $this->assertContains('time', array_column($items, 'key'));
        $this->assertContains('insights', array_column($items, 'key'));
        $this->assertSame(['dashboard', 'search'], array_column(array_filter($items, static fn (array $i): bool => $i['always']), 'key'));
    }

    public function test_dashboard_lists_areas_from_the_same_list(): void {
        $this->allowPortal($this->customer, ['diary', 'tickets']);
        $this->actingAs($this->portalUser, 'customer');

        $page = $this->get(route('customer.dashboard'))->assertOk();
        $page->assertSee(__('customer_portal.dashboard_areas.title'))
            ->assertSee(__('customer_portal.nav.diary'))
            ->assertSee(__('customer_portal.nav.tickets'))
            ->assertSee(__('customer_portal.nav.catalog'))
            ->assertDontSee(__('customer_portal.nav.invoices'));
        $this->assertSame(['diary', 'tickets', 'catalog', 'known_errors'], array_column($page->viewData('areas'), 'key'));
    }

    public function test_every_navigation_item_renders_its_page(): void {
        $this->allowPortal($this->customer);
        $this->actingAs($this->portalUser, 'customer');

        foreach (app(PortalNavigation::class)->items($this->customer->refresh()) as $item) {
            if ($item['key'] === 'billing') {
                continue; // nur mit Abrechnungskonto, dort eigener Test
            }
            $this->get(route($item['route']))->assertOk();
        }
    }

    public function test_without_release_the_dashboard_explains_the_empty_state(): void {
        $this->customer->forceFill(['portal_settings' => ['enabled' => true, 'capabilities' => [], 'time_detail' => 'none', 'time_scope' => 'published']])->save();
        $this->actingAs($this->portalUser, 'customer');

        // Übersicht und Suche bleiben immer erreichbar, Fachbereiche fehlen.
        $this->get(route('customer.dashboard'))->assertOk()
            ->assertSee(__('Für Ihren Zugang sind noch keine Bereiche freigegeben.'))
            ->assertSee(route('customer.search'), false)
            ->assertDontSee(route('customer.diary.index'), false);
    }

    public function test_capability_catalog_contains_insights(): void {
        $this->assertSame('insights', PortalCapability::Insights->value);
        $this->assertNull(PortalCapability::Insights->moduleFlag());
    }
}
