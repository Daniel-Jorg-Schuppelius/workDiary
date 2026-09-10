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
use App\Models\{Customer, ForeignCustomer, LexofficeArticle};
use App\Models\Domain\{DomainProjection, DomainProviderConnection};
use App\Models\Reselling\{ResalePriceEntry, ResaleSubscription};
use App\Services\Reselling\Register\DomainSubscriptionSync;
use Carbon\CarbonImmutable;
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
        $this->assertSame(['domains' => 5, 'created' => 5, 'updated' => 0, 'unchanged' => 0, 'ended' => 0, 'skipped_gone' => false], $result);

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

    public function test_empty_projection_list_does_not_end_the_domain_subscriptions(): void {
        // Review 2026-09-10 (B6): Plugin aus, Sync-Fehler oder leere Tabelle sind kein Beleg für „verschwunden".
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->projection($connection, 'bleibt.de', ['customer_id' => $customer->id]);
        app(DomainSubscriptionSync::class)->sync($this->organization);
        $this->assertSame(SubscriptionStatus::Active, ResaleSubscription::query()->where('external_id', 'bleibt.de')->value('status'));

        DomainProjection::query()->delete();
        $result = app(DomainSubscriptionSync::class)->sync($this->organization);

        $this->assertTrue($result['skipped_gone']);
        $this->assertSame(0, $result['ended']);
        $this->assertSame(SubscriptionStatus::Active, ResaleSubscription::query()->where('external_id', 'bleibt.de')->value('status'), 'ohne Projektionen wird nichts beendet');
        $this->artisan('resale:sync-domains', ['--org' => $this->organization->id])->expectsOutputToContain('keine Domain-Projektionen')->assertSuccessful();
    }

    public function test_ended_domain_without_expiration_keeps_its_end_date_across_runs(): void {
        // Review 2026-09-10 (B6): DELETED ohne Ablaufdatum → Ende einmal am Stichtag, danach fest — kein tägliches „geändert".
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->projection($connection, 'weg.de', ['customer_id' => $customer->id, 'status' => 'DELETED', 'expiration_at' => null]);

        $first = app(DomainSubscriptionSync::class)->sync($this->organization, CarbonImmutable::parse('2026-09-04'));
        $this->assertSame(1, $first['created']);
        $subscription = ResaleSubscription::query()->where('external_id', 'weg.de')->firstOrFail();
        $this->assertSame(SubscriptionStatus::Ended, $subscription->status);
        $this->assertSame('2026-09-04', $subscription->ends_on?->toDateString());

        $second = app(DomainSubscriptionSync::class)->sync($this->organization, CarbonImmutable::parse('2026-09-05'));
        $this->assertSame(1, $second['unchanged'], 'zweiter Lauf am Folgetag: unverändert');
        $this->assertSame('2026-09-04', $subscription->fresh()?->ends_on?->toDateString(), 'Ende wandert nicht mit dem Stichtag');

        // Auch wenn sich etwas anderes ändert (Preis), bleibt das einmal gesetzte Ende stehen.
        DomainProjection::query()->where('external_domain', 'weg.de')->update(['renewal_price' => '9.99']);
        $third = app(DomainSubscriptionSync::class)->sync($this->organization, CarbonImmutable::parse('2026-09-06'));
        $this->assertSame(1, $third['updated']);
        $this->assertSame('2026-09-04', $subscription->fresh()?->ends_on?->toDateString());
    }

    public function test_sale_price_and_article_come_from_the_tld_catalog_and_the_lexoffice_article(): void {
        // Review 2026-09-10 (A6): Verkaufspreis je TLD aus dem Katalog (UVP), Artikelbezug „Domain .de"; manuelle Pflege bleibt.
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        ResalePriceEntry::query()->create([
            'organization_id' => $this->organization->id, 'provider' => SubscriptionProvider::DomainReselling, 'product' => '.de', 'term_months' => 12, 'interval' => 'yearly',
            'valid_from' => '2026-01-01', 'purchase_unit_price' => '8.87', 'list_unit_price' => '15.00', 'currency' => 'EUR',
        ]);
        ResalePriceEntry::query()->create([
            'organization_id' => $this->organization->id, 'provider' => SubscriptionProvider::DomainReselling, 'product' => 'co.uk', 'term_months' => 12, 'interval' => 'yearly',
            'valid_from' => '2026-01-01', 'purchase_unit_price' => '12.00', 'list_unit_price' => null, 'currency' => 'EUR',
        ]);
        ResalePriceEntry::query()->create([
            'organization_id' => $this->organization->id, 'provider' => SubscriptionProvider::DomainReselling, 'product' => '.com', 'term_months' => 12, 'interval' => 'yearly',
            'valid_from' => '2027-01-01', 'purchase_unit_price' => '10.00', 'list_unit_price' => '18.00', 'currency' => 'EUR',
        ]);
        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-de', 'name' => 'Domain .de', 'type' => 'SERVICE', 'unit_name' => 'Jahr',
            'net_unit_price' => '14.00', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-com-1', 'name' => '.com-Domain', 'type' => 'SERVICE', 'unit_name' => 'Monat',
            'net_unit_price' => '1.50', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-net-1', 'name' => 'Domain .net', 'type' => 'SERVICE', 'unit_name' => 'Jahr',
            'net_unit_price' => '20.00', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-net-2', 'name' => '.net-Domain', 'type' => 'SERVICE', 'unit_name' => 'Jahr',
            'net_unit_price' => '21.00', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->projection($connection, 'klimpel-baeder.de', ['customer_id' => $customer->id]);
        $this->projection($connection, 'shop.example.co.uk', ['customer_id' => $customer->id]);
        $this->projection($connection, 'beispiel.com', ['customer_id' => $customer->id]);
        $this->projection($connection, 'zweideutig.net', ['customer_id' => $customer->id]);
        $this->projection($connection, 'manuell.de', ['customer_id' => $customer->id]);
        ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'manuell.de', 'label' => 'manuell.de',
            'customer_id' => $customer->id, 'quantity' => 1, 'starts_on' => '2023-03-15', 'currency' => 'EUR', 'status' => 'active', 'sale_unit_price' => '99.00',
        ]);

        app(DomainSubscriptionSync::class)->sync($this->organization);

        $de = ResaleSubscription::query()->where('external_id', 'klimpel-baeder.de')->firstOrFail();
        $this->assertSame('15.0000', $de->sale_unit_price?->getAmount(), 'UVP der Katalogzeile .de');
        $this->assertSame($article->id, $de->lexoffice_article_id, 'Artikel „Domain .de"');
        $this->assertSame('15.00', $de->periods()->first()?->expected_sale?->getAmount(), 'Perioden tragen den Verkauf');

        $uk = ResaleSubscription::query()->where('external_id', 'shop.example.co.uk')->firstOrFail();
        $this->assertSame('12.0000', $uk->sale_unit_price?->getAmount(), 'Katalog „co.uk" ohne UVP → Katalogpreis; längster Suffix zuerst');

        $com = ResaleSubscription::query()->where('external_id', 'beispiel.com')->firstOrFail();
        $this->assertSame('18.0000', $com->sale_unit_price?->getAmount(), 'Katalog .com gilt erst 2027 → Artikelpreis 1,50 €/Monat × 12');
        $this->assertNotNull($com->lexoffice_article_id, 'Artikel „.com-Domain"');

        $net = ResaleSubscription::query()->where('external_id', 'zweideutig.net')->firstOrFail();
        $this->assertNull($net->lexoffice_article_id, 'zwei passende Artikel → keiner');
        $this->assertNull($net->sale_unit_price);

        $manual = ResaleSubscription::query()->where('external_id', 'manuell.de')->firstOrFail();
        $this->assertSame('99.0000', $manual->sale_unit_price?->getAmount(), 'manueller Verkaufspreis bleibt');

        // Zweiter Lauf: unverändert, Preise bleiben.
        $again = app(DomainSubscriptionSync::class)->sync($this->organization);
        $this->assertSame(5, $again['unchanged']);
        $this->assertSame('15.0000', $de->fresh()?->sale_unit_price?->getAmount());
    }

    public function test_subscription_takes_a_later_projection_holder_and_keeps_register_decisions(): void {
        // Review 2026-09-10 (G, Halterwechsel): Halter aus der Projektion, „solange das Register keinen
        // entschieden hat" (Feature 152, MVP-763). Nachgetragener Halter → Abo folgt; Entscheidung im Register
        // (Inbox „own", Dialog, Halterwechsel im Abgleich) überlebt jeden Lauf — auch den per Befehl.
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $first = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Erster Halter GmbH']);
        $second = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Zweiter Halter GmbH']);
        $sync = app(DomainSubscriptionSync::class);

        // Ohne Halter in der Projektion: Inbox. Bekommt die Projektion später einen, folgt das Abo.
        $projection = $this->projection($connection, 'wechsel.de');
        $sync->sync($this->organization);
        $subscription = ResaleSubscription::query()->where('external_id', 'wechsel.de')->firstOrFail();
        $this->assertFalse($subscription->hasHolder());
        $this->assertSame(1, ResaleSubscription::query()->unassigned()->count());
        $projection->forceFill(['customer_id' => $first->id])->save();
        $result = $sync->sync($this->organization);
        $this->assertSame(1, $result['unchanged'], 'der Halter steht nicht im Hash — kein „geändert"');
        $subscription->refresh();
        $this->assertSame($first->id, $subscription->customer_id, 'Abo folgt dem nachgetragenen Halter der Projektion');
        $this->assertNull($subscription->foreign_customer_id);
        $this->assertFalse($subscription->is_own_holding);
        $this->assertSame(4, $subscription->periods()->count(), 'Perioden bleiben, kein Neuanlegen durch den Halter');

        // Entscheidung im Register (wie Halterwechsel im Abgleich): die Projektion steht anders, das Abo bleibt.
        $subscription->forceFill(['customer_id' => $second->id])->save();
        $projection->forceFill(['customer_id' => $first->id])->save();
        $sync->sync($this->organization);
        $this->assertSame($second->id, $subscription->fresh()?->customer_id, 'Entscheidung im Register überlebt den Lauf');

        // Eigener Bestand (Inbox „own") ebenso — auch wenn die Projektion später einen Kunden nennt.
        $own = $this->projection($connection, 'eigen.de');
        $sync->sync($this->organization);
        $ownSubscription = ResaleSubscription::query()->where('external_id', 'eigen.de')->firstOrFail();
        $ownSubscription->forceFill(['is_own_holding' => true])->save();
        $own->forceFill(['customer_id' => $first->id])->save();
        $sync->sync($this->organization);
        $ownSubscription->refresh();
        $this->assertTrue($ownSubscription->is_own_holding, 'eigener Bestand bleibt eigener Bestand');
        $this->assertNull($ownSubscription->customer_id);

        // Der Befehl macht dasselbe wie der Service (Zeitplan 05:10, nach domain:sync): Halter weg aus der
        // Projektion nimmt dem Abo keinen entschiedenen Halter.
        $projection->forceFill(['customer_id' => null])->save();
        $this->artisan('resale:sync-domains', ['--org' => $this->organization->id])
            ->expectsOutputToContain('2 Domains, 0 neu, 0 geändert, 2 unverändert')
            ->assertSuccessful();
        $this->assertSame($second->id, $subscription->fresh()?->customer_id);
        $this->assertTrue($ownSubscription->fresh()?->is_own_holding);
        $this->assertSame(0, ResaleSubscription::query()->unassigned()->count());
    }

    public function test_subscription_follows_a_holder_change_in_the_projection_when_the_register_never_decided(): void {
        // Review 2026-09-10 (G, Halterwechsel): die Projektion (083) ist die technische Wahrheit, das Abo spiegelt
        // ihren Halter („Ableitung automatisch, keine Doppelpflege", Feature 152). Zieht die Domain in der
        // Domainverwaltung zu einem anderen Kunden oder Endkunden um und im Register wurde NIE entschieden
        // (der Halter stammt selbst aus der Projektion), folgt das Abo — sonst geht der Rechnungsvorschlag an
        // den falschen Empfänger.
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $first = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Erster Halter GmbH']);
        $second = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Zweiter Halter GmbH']);
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH']);
        $end = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Kaik']);
        $sync = app(DomainSubscriptionSync::class);
        $projection = $this->projection($connection, 'umzug.de', ['customer_id' => $first->id]);
        $sync->sync($this->organization);
        $subscription = ResaleSubscription::query()->where('external_id', 'umzug.de')->firstOrFail();
        $this->assertSame($first->id, $subscription->customer_id, 'Halter aus der Projektion');

        // Kunde → anderer Kunde.
        $projection->forceFill(['customer_id' => $second->id])->save();
        $result = $sync->sync($this->organization);
        $this->assertSame(1, $result['unchanged'], 'Halter steht nicht im Hash');
        $subscription->refresh();
        $this->assertSame($second->id, $subscription->customer_id, 'Halter aus der Projektion folgt ihrem Wechsel');
        $this->assertNull($subscription->foreign_customer_id);
        $this->assertFalse($subscription->is_own_holding);

        // Kunde → Endkunde eines Partners (Rechnung an den Partner).
        $projection->forceFill(['customer_id' => null, 'foreign_customer_id' => $end->id])->save();
        $sync->sync($this->organization);
        $subscription->refresh();
        $this->assertNull($subscription->customer_id);
        $this->assertSame($end->id, $subscription->foreign_customer_id);
        $this->assertSame($partner->id, $subscription->billedTo()?->id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(4, $subscription->periods()->count(), 'Perioden bleiben, kein Neuanlegen durch den Halterwechsel');
    }
}
