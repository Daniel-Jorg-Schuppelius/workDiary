<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainSubscriptionSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{SubscriptionKind, SubscriptionProvider, SubscriptionStatus};
use App\Models\{Customer, ForeignCustomer};
use App\Models\Domain\{DomainProjection, DomainProviderConnection};
use App\Models\Reselling\ResaleSubscription;
use App\Services\Reselling\Register\DomainSubscriptionSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Domains als Abo-Art (Feature 152, MVP-763).
 */
class DomainSubscriptionSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    private function projection(DomainProviderConnection $connection, string $domain, array $attributes = []): DomainProjection {
        return DomainProjection::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => $domain,
            'domain_hash' => DomainProjection::hashFor($domain),
            'status' => 'ACTIVE',
            'renewal_mode' => 'AUTORENEW',
            'registration_at' => '2023-03-15',
            'expiration_at' => '2027-03-15',
            'renewal_price' => '8.87',
            'renewal_currency' => 'EUR',
        ], $attributes));
    }

    public function test_projections_become_domain_subscriptions_with_holders_and_periods(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH']);
        $end = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Kaik']);
        $this->projection($connection, 'klimpel-baeder.de', ['customer_id' => $customer->id]);
        $this->projection($connection, 'kaik.de', ['foreign_customer_id' => $end->id, 'registration_at' => '2024-11-02']);
        $this->projection($connection, 'eigene.de', ['is_own_holding' => true]);
        $this->projection($connection, 'ohne-halter.de');
        $this->projection($connection, 'auslaufend.de', ['customer_id' => $customer->id, 'renewal_mode' => 'AUTOEXPIRE', 'expiration_at' => '2026-12-31']);

        $result = app(DomainSubscriptionSync::class)->sync($this->organization);
        $this->assertSame(['domains' => 5, 'created' => 5, 'updated' => 0, 'unchanged' => 0, 'ended' => 0], $result);

        $klimpel = ResaleSubscription::query()->where('external_id', 'klimpel-baeder.de')->firstOrFail();
        $this->assertSame(SubscriptionKind::Domain, $klimpel->kind);
        $this->assertSame(SubscriptionProvider::DomainReselling, $klimpel->provider);
        $this->assertSame($customer->id, $klimpel->customer_id);
        $this->assertSame('8.8700', $klimpel->purchase_unit_price?->getAmount());
        $this->assertSame(['2023-03-15', '2024-03-15', '2025-03-15', '2026-03-15'], $klimpel->periods->map(static fn($p) => $p->starts_on->toDateString())->all());

        $kaik = ResaleSubscription::query()->where('external_id', 'kaik.de')->firstOrFail();
        $this->assertSame($end->id, $kaik->foreign_customer_id);
        $this->assertSame($partner->id, $kaik->billedTo()?->id);
        $this->assertTrue(ResaleSubscription::query()->where('external_id', 'eigene.de')->value('is_own_holding'));
        $this->assertSame(1, ResaleSubscription::query()->unassigned()->count(), 'ohne-halter.de wartet in der Inbox');
        $this->assertSame(SubscriptionStatus::Cancelled, ResaleSubscription::query()->where('external_id', 'auslaufend.de')->value('status'));

        // Zweiter Lauf: unverändert; Halterentscheidung im Register bleibt.
        ResaleSubscription::query()->where('external_id', 'ohne-halter.de')->update(['is_own_holding' => true]);
        $again = app(DomainSubscriptionSync::class)->sync($this->organization);
        $this->assertSame(5, $again['unchanged']);
        $this->assertTrue(ResaleSubscription::query()->where('external_id', 'ohne-halter.de')->value('is_own_holding'));

        // Domain verschwindet aus der Projektion → Abo endet am Stichtag.
        DomainProjection::query()->where('external_domain', 'eigene.de')->delete();
        $third = app(DomainSubscriptionSync::class)->sync($this->organization);
        $this->assertSame(1, $third['ended']);
        $this->assertSame(SubscriptionStatus::Ended, ResaleSubscription::query()->where('external_id', 'eigene.de')->value('status'));

        $this->artisan('resale:sync-domains', ['--org' => $this->organization->id])->expectsOutputToContain('4 Domains')->assertSuccessful();
    }
}
