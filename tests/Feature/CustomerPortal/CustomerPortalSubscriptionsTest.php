<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalSubscriptionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Enums\CustomerPortal\PortalCapability;
use App\Enums\Reselling\{BillingFrequency, PeriodStatus, SubscriptionStatus};
use App\Models\{Customer, ForeignCustomer, User};
use App\Models\Reselling\ResaleSubscription;
use App\Services\CustomerPortal\PortalVisibility;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reselling\Register\PeriodPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Portal „meine Abos" (Feature 152, Prozesse 6): Default-Deny der Capability,
 * Bestand des Kunden und seiner Endkunden ohne Preise und Belege, fremde
 * Kunden unsichtbar, Modul-Gate module.reselling.
 */
final class CustomerPortalSubscriptionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private ForeignCustomer $endCustomer;

    private ResaleSubscription $own;

    private ResaleSubscription $viaEndCustomer;

    private ResaleSubscription $foreign;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->travelTo('2026-09-10');

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schub- und Schleppreederei']);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
        $this->endCustomer = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Märkische Bunker']);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Geheimkunde GmbH']);

        $this->own = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $this->customer->id, 'quantity' => 5, 'starts_on' => '2026-01-01']);
        $this->viaEndCustomer = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'foreign_customer_id' => $this->endCustomer->id, 'quantity' => 3, 'starts_on' => '2025-10-18', 'external_id' => 'ent-3']);
        $this->foreign = $this->subscription(['label' => 'Geheim-Abo Business Standard', 'customer_id' => $other->id, 'external_id' => 'ent-x']);
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes): ResaleSubscription {
        return ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-' . uniqid(),
            'label' => 'Abo', 'quantity' => 1, 'starts_on' => '2026-01-01', 'term_months' => 12, 'interval' => BillingFrequency::Yearly, 'renewal' => 'auto',
            'purchase_unit_price' => '187.92', 'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => SubscriptionStatus::Active,
        ], $attributes));
    }

    public function test_without_capability_index_and_show_are_404_and_nav_hides_the_entry(): void {
        $this->allowPortal($this->customer, ['diary', 'invoices']); // subscriptions fehlt bewusst (Default-Deny)

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.subscriptions.index'))->assertNotFound();
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.subscriptions.show', $this->own))->assertNotFound();
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.dashboard'))
            ->assertOk()->assertDontSee(route('customer.subscriptions.index'));
    }

    public function test_index_lists_own_and_end_customer_subscriptions_without_prices(): void {
        // Beendet vor mehr als zwölf Monaten: raus; beendet vor drei Monaten: noch sichtbar.
        $this->subscription(['label' => 'Altes Backup-Abo', 'customer_id' => $this->customer->id, 'status' => SubscriptionStatus::Ended, 'starts_on' => '2022-01-01', 'ends_on' => '2024-12-31']);
        $this->subscription(['label' => 'Kürzlich beendetes Postfach', 'customer_id' => $this->customer->id, 'status' => SubscriptionStatus::Ended, 'starts_on' => '2024-06-01', 'ends_on' => '2026-06-30']);
        $this->subscription(['label' => 'Gekündigte Domain', 'customer_id' => $this->customer->id, 'kind' => 'domain', 'status' => SubscriptionStatus::Cancelled, 'renewal' => 'cancel', 'starts_on' => '2025-03-01', 'ends_on' => '2026-02-28']);

        $page = $this->actingAs($this->portalUser, 'customer')->get(route('customer.subscriptions.index'))->assertOk();

        $page->assertSee(route('customer.subscriptions.index'), false)
            ->assertSee('Microsoft 365 Business Premium')
            ->assertSee('Exchange Online (Plan 1)')
            ->assertSee('Märkische Bunker')
            ->assertSee(__('resale_portal.holder.end_customer'))
            ->assertSee('Kürzlich beendetes Postfach')
            ->assertSee('Gekündigte Domain')
            ->assertDontSee('Altes Backup-Abo')
            ->assertDontSee('Geheim-Abo Business Standard')
            ->assertDontSee('Geheimkunde GmbH');

        // Nächste Periode aus der Planung: 01.01.2027 (eigenes Abo) und 18.10.2026 (Endkunde, in 38 Tagen).
        $page->assertSee('01.01.2027')->assertSee('18.10.2026')->assertSee(__('resale_portal.term.running'));

        // Nur Bestand: keine Verkaufs-/Einkaufspreise, keine Soll-Beträge.
        $page->assertDontSee('247,20')->assertDontSee('187,92')->assertDontSee('1.236,00')->assertDontSee('741,60');
    }

    public function test_show_lists_periods_with_neutral_status_and_hides_foreign_subscriptions(): void {
        $planner = new PeriodPlanner;
        $planner->sync($this->viaEndCustomer);
        $planner->sync($this->foreign);
        $this->viaEndCustomer->periods()->where('starts_on', '2025-10-18')->update(['status' => PeriodStatus::Billed->value, 'decided_at' => now()]);

        $page = $this->actingAs($this->portalUser, 'customer')->get(route('customer.subscriptions.show', $this->viaEndCustomer))->assertOk();
        $page->assertSee('Exchange Online (Plan 1)')
            ->assertSee('Märkische Bunker')
            ->assertSee('18.10.2025 – 17.10.2026')
            ->assertSee('18.10.2026 – 17.10.2027')
            ->assertSee(__('resale_portal.period_status.billed'))
            ->assertSee(__('resale_portal.period_status.open'))
            ->assertDontSee(__('resale.period_status.billed'), false)
            ->assertDontSee('741,60')
            ->assertDontSee('247,20');

        // Fremder Kunde: nicht vorhanden (404), auch mit gültigem Sqid.
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.subscriptions.show', $this->foreign))->assertNotFound();
    }

    public function test_dashboard_tile_counts_the_visible_subscriptions_only_with_capability(): void {
        // Vor mehr als zwölf Monaten beendet: nicht im Bestand; der fremde Kunde zählt nie.
        $this->subscription(['label' => 'Altes Backup-Abo', 'customer_id' => $this->customer->id, 'status' => SubscriptionStatus::Ended, 'starts_on' => '2022-01-01', 'ends_on' => '2024-12-31']);

        $page = $this->actingAs($this->portalUser, 'customer')->get(route('customer.dashboard'))->assertOk();
        $page->assertSee(route('customer.subscriptions.index'), false)
            ->assertSee((string) __('resale_portal.dashboard.tile'));
        $stats = $page->viewData('stats');
        $this->assertIsArray($stats);
        $this->assertSame(2, $stats['subscriptions'] ?? null, 'eigenes Abo + Abo des Endkunden');

        // Ohne Freigabe (Default-Deny): keine Kachel, kein Zähler. Das Portalkonto hält die
        // Kundenrelation aus der ersten Anfrage — neu laden, sonst greifen die alten Sichtbarkeiten.
        $this->allowPortal($this->customer, ['diary']);
        $this->portalUser->refresh();
        $page = $this->actingAs($this->portalUser, 'customer')->get(route('customer.dashboard'))->assertOk();
        $page->assertDontSee(route('customer.subscriptions.index'), false)
            ->assertDontSee((string) __('resale_portal.dashboard.tile'));
        $stats = $page->viewData('stats');
        $this->assertIsArray($stats);
        $this->assertArrayNotHasKey('subscriptions', $stats);
    }

    public function test_capability_is_unavailable_without_the_reselling_module(): void {
        $visibility = app(PortalVisibility::class);
        $this->assertTrue($visibility->allows($this->customer, PortalCapability::Subscriptions));
        $this->assertSame('module.reselling', PortalCapability::Subscriptions->moduleFlag());

        config(['license.feature_overrides' => ['module.reselling' => false]]);
        app(FeatureFlagResolver::class)->flush();

        $this->assertFalse($visibility->capabilityAvailable(PortalCapability::Subscriptions));
        $this->assertFalse($visibility->allows($this->customer, PortalCapability::Subscriptions), 'Freigabe ohne Modul zählt nicht');
        $this->assertNotContains(PortalCapability::Subscriptions, $visibility->availableCapabilities());
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.subscriptions.index'))->assertNotFound();
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.dashboard'))->assertOk()->assertDontSee(route('customer.subscriptions.index'));

        config(['license.feature_overrides' => []]);
        app(FeatureFlagResolver::class)->flush();
    }
}
