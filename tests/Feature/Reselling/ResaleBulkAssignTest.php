<?php
/*
 * Created on   : Tue Oct 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleBulkAssignTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\CompanyMappingMode;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Domain\{DomainProjection, DomainProviderConnection};
use App\Models\Reselling\{CompanyMapping, ResaleAccountAllocation, ResaleSubscription};
use App\Services\Reselling\Register\{DomainSubscriptionSync, PeriodPlanner};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sammelzuordnung des Halters aus der Abo-Liste (Feature 152, Nachbesserung
 * 2026-10-27): Filter „ohne Halter", Auswahl, ein Halter für alle; Domains
 * schreiben in die Domainverwaltung durch, Firmen werden gemerkt.
 */
class ResaleBulkAssignTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-27');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    private function projection(DomainProviderConnection $connection, string $domain): DomainProjection {
        return DomainProjection::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_domain' => $domain,
            'domain_hash' => DomainProjection::hashFor($domain), 'status' => 'ACTIVE', 'renewal_mode' => 'AUTORENEW',
            'registration_at' => '2025-01-03', 'expiration_at' => '2027-01-03', 'renewal_price' => '8.87', 'renewal_currency' => 'EUR',
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function imported(string $company, array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ext-' . fake()->unique()->numerify('#####'),
            'company_name' => $company, 'label' => 'Microsoft 365 Business Standard', 'quantity' => 1, 'starts_on' => '2026-01-15', 'term_months' => 12,
            'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '140.40',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_list_filters_unassigned_and_offers_bulk_and_row_assignment(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH']);
        $this->imported('Badhaus Bäder GmbH', ['customer_id' => $customer->id, 'label' => 'Exchange Online Plan 1']);
        $open = $this->imported('Muster Bau GmbH');
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $this->projection($connection, 'dein-pc-fachmann.de');
        app(DomainSubscriptionSync::class)->sync($this->organization);

        $page = $this->actingAs($admin)->get(route('finance.resale.subscriptions', ['unassigned' => 1]))->assertOk();
        $page->assertSee('Muster Bau GmbH')->assertSee('dein-pc-fachmann.de')->assertDontSee('Exchange Online Plan 1')
            ->assertSee('data-bulk-select-all', false)
            ->assertSee(route('finance.resale.subscriptions.bulk.assign.create'), false)
            ->assertSee(route('finance.resale.subscriptions.bulk.assign.create', ['ids' => [$open->sqid]]), false)
            ->assertSee(__('resale.filter.unassigned_only'));

        // Inbox: Abos ohne Firmenname (Domains) lassen sich dort nicht zuordnen — sie führen in die Liste.
        $this->actingAs($admin)->get(route('finance.resale.inbox'))->assertOk()
            ->assertSee(__('resale.inbox.no_company'))->assertSee(__('resale.inbox.in_list'))
            ->assertSee(route('finance.resale.subscriptions', ['unassigned' => 1]), false);

        // Dialog: die gewählten Abos, Modus, Halter; „merken" nur mit Firmenname.
        $domain = ResaleSubscription::query()->where('external_id', 'dein-pc-fachmann.de')->firstOrFail();
        $this->actingAs($admin)->get(route('finance.resale.subscriptions.bulk.assign.create', ['ids' => [$domain->sqid]]))->assertOk()
            ->assertSee('dein-pc-fachmann.de')->assertSee(route('finance.resale.subscriptions.bulk.assign'), false)
            ->assertDontSee(__('resale.bulk.remember'));
        $this->actingAs($admin)->get(route('finance.resale.subscriptions.bulk.assign.create', ['ids' => [$open->sqid, $domain->sqid]]))->assertOk()
            ->assertSee('Muster Bau GmbH')->assertSee(__('resale.bulk.remember'))->assertSee(__('resale.inbox.mode_partner'));
        $this->actingAs($admin)->get(route('finance.resale.subscriptions.bulk.assign.create'))->assertSessionHasErrors('ids');
    }

    public function test_bulk_assign_writes_domains_through_to_the_domain_management_and_survives_the_sync(): void {
        $admin = $this->orgAdmin();
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $this->projection($connection, 'dein-pc-fachmann.de');
        $this->projection($connection, 'dein-server-fachmann.de');
        $this->projection($connection, 'fernblick-optik.de');
        app(DomainSubscriptionSync::class)->sync($this->organization);
        $own = ResaleSubscription::query()->where('external_id', 'dein-pc-fachmann.de')->firstOrFail();
        $ownToo = ResaleSubscription::query()->where('external_id', 'dein-server-fachmann.de')->firstOrFail();
        $fernblick = ResaleSubscription::query()->where('external_id', 'fernblick-optik.de')->firstOrFail();
        $nordlicht = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH']);
        $end = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $nordlicht->id, 'name' => 'Fernblick Augenoptik']);
        $this->assertSame(3, ResaleSubscription::query()->unassigned()->count());

        // Zwei Domains auf einmal in den eigenen Bestand.
        $this->actingAs($admin)->post(route('finance.resale.subscriptions.bulk.assign'), ['ids' => [$own->sqid, $ownToo->sqid], 'mode' => 'own'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertTrue((bool) $own->refresh()->is_own_holding);
        $this->assertTrue((bool) $ownToo->refresh()->is_own_holding);
        $this->assertTrue((bool) DomainProjection::query()->where('external_domain', 'dein-pc-fachmann.de')->value('is_own_holding'), 'Durchschreiben in die Domainverwaltung');
        $this->assertSame('p:own', $own->refresh()->sync_status);

        // Eine Domain an den Endkunden eines Partners — wie der Domain-Sync: Partner als Rechnungsempfänger.
        $this->actingAs($admin)->post(route('finance.resale.subscriptions.bulk.assign'), ['ids' => [$fernblick->sqid], 'mode' => 'foreign', 'foreign_customer_id' => $end->sqid])
            ->assertRedirect()->assertSessionHas('success');
        $fernblick->refresh();
        $this->assertSame($end->id, $fernblick->foreign_customer_id);
        $this->assertSame($nordlicht->id, $fernblick->customer_id);
        $this->assertSame($nordlicht->id, $fernblick->billedTo()?->id);
        $projection = DomainProjection::query()->where('external_domain', 'fernblick-optik.de')->firstOrFail();
        $this->assertSame($end->id, $projection->foreign_customer_id);
        $this->assertSame($nordlicht->id, $projection->customer_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'resale_subscription.holder_assigned', 'auditable_id' => $fernblick->id]);

        // Der nächste Domain-Sync sieht den gespiegelten Halter und lässt alles stehen.
        app(DomainSubscriptionSync::class)->sync($this->organization);
        $this->assertTrue((bool) $own->refresh()->is_own_holding);
        $this->assertSame($end->id, $fernblick->refresh()->foreign_customer_id);
        $this->assertSame(0, ResaleSubscription::query()->unassigned()->count());
    }

    public function test_bulk_assign_remembers_the_company_and_skips_derived_assignments(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Muster Bau GmbH']);
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH']);
        $first = $this->imported('Muster Bau GmbH');
        $second = $this->imported('Muster Bau GmbH', ['label' => 'Exchange Online Plan 1']);
        $derived = $this->imported('Muster Bau GmbH', ['parent_id' => $first->id, 'sync_status' => ResaleAccountAllocation::DERIVED_PREFIX . '1']);

        $this->actingAs($admin)->post(route('finance.resale.subscriptions.bulk.assign'), [
            'ids' => [$first->sqid, $second->sqid, $derived->sqid], 'mode' => 'customer', 'customer_id' => $customer->sqid, 'remember' => 1,
        ])->assertRedirect()->assertSessionHas('warning');
        $this->assertSame($customer->id, $first->refresh()->customer_id);
        $this->assertSame($customer->id, $second->refresh()->customer_id);
        $this->assertNull($derived->refresh()->customer_id, 'abgeleitete Abtretung: Halter kommt aus der Aufteilung');
        $mapping = CompanyMapping::query()->where('company_name', 'Muster Bau GmbH')->sole();
        $this->assertSame(CompanyMappingMode::Customer, $mapping->mode);
        $this->assertSame($customer->id, $mapping->customer_id);

        // Partner-Modus legt den Endkunden je Firma an; ohne „merken" keine Firmenzuordnung.
        $third = $this->imported('Neu Bau GmbH');
        $this->actingAs($admin)->post(route('finance.resale.subscriptions.bulk.assign'), [
            'ids' => [$third->sqid], 'mode' => 'partner', 'customer_id' => $partner->sqid, 'remember' => 0,
        ])->assertRedirect()->assertSessionHas('success');
        $foreign = ForeignCustomer::query()->where('customer_id', $partner->id)->where('name', 'Neu Bau GmbH')->sole();
        $this->assertSame($foreign->id, $third->refresh()->foreign_customer_id);
        $this->assertSame($partner->id, $third->refresh()->billedTo()?->id);
        $this->assertSame(0, CompanyMapping::query()->where('company_name', 'Neu Bau GmbH')->count());

        // Validierung: ohne Auswahl, ohne Kunden.
        $this->actingAs($admin)->postJson(route('finance.resale.subscriptions.bulk.assign'), ['mode' => 'own'])->assertStatus(422)->assertJsonValidationErrors(['ids']);
        $this->actingAs($admin)->postJson(route('finance.resale.subscriptions.bulk.assign'), ['ids' => [$third->sqid], 'mode' => 'customer'])->assertStatus(422)->assertJsonValidationErrors(['customer_id']);
    }
}
