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
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Domain\{DomainProjection, DomainProviderConnection, DomainResellerAccount};
use App\Models\Reselling\{ResalePriceEntry, ResaleSubscription};
use App\Plugins\Lexoffice\Models\LexofficeArticle;
use App\Services\Reselling\Register\DomainSubscriptionSync;
use App\Settings\SettingScope;
use App\Support\Setting;
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
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH']);
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH']);
        $end = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Zahlenwerk']);
        $this->projection($connection, 'badhaus-baeder.de', ['customer_id' => $customer->id]);
        $this->projection($connection, 'zahlenwerk.de', ['foreign_customer_id' => $end->id, 'registration_at' => '2024-11-02']);
        $this->projection($connection, 'eigene.de', ['is_own_holding' => true]);
        $this->projection($connection, 'ohne-halter.de');
        $this->projection($connection, 'auslaufend.de', ['customer_id' => $customer->id, 'renewal_mode' => 'AUTOEXPIRE', 'expiration_at' => '2026-12-31']);

        $result = app(DomainSubscriptionSync::class)->sync($this->organization);
        $this->assertSame(['domains' => 5, 'created' => 5, 'updated' => 0, 'unchanged' => 0, 'ended' => 0, 'skipped_gone' => false], $result);

        $badhaus = ResaleSubscription::query()->where('external_id', 'badhaus-baeder.de')->firstOrFail();
        $this->assertSame(SubscriptionKind::Domain, $badhaus->kind);
        $this->assertSame(SubscriptionProvider::DomainReselling, $badhaus->provider);
        $this->assertSame($customer->id, $badhaus->customer_id);
        $this->assertSame('8.8700', $badhaus->purchase_unit_price?->getAmount());
        $this->assertSame(['2023-03-15', '2024-03-15', '2025-03-15', '2026-03-15'], $badhaus->periods->map(static fn($p) => $p->starts_on->toDateString())->all());

        $zahlenwerk = ResaleSubscription::query()->where('external_id', 'zahlenwerk.de')->firstOrFail();
        $this->assertSame($end->id, $zahlenwerk->foreign_customer_id);
        $this->assertSame($partner->id, $zahlenwerk->billedTo()?->id);
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

    public function test_idn_domains_carry_the_readable_name_as_label_and_punycode_as_id(): void {
        // Pilot 2026-10-31: „xn--…“ stand als Bezeichnung auf Seiten und Rechnungen; die Kennung des Anbieters bleibt Punycode.
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH']);
        $this->projection($connection, 'xn--onlinehndler-beispiel-c2b.de', ['customer_id' => $customer->id]);

        app(DomainSubscriptionSync::class)->sync($this->organization);
        $subscription = ResaleSubscription::query()->where('external_id', 'xn--onlinehndler-beispiel-c2b.de')->firstOrFail();
        $this->assertSame('onlinehändler-beispiel.de', $subscription->label);
        $this->assertSame(['domains' => 1, 'created' => 0, 'updated' => 0, 'unchanged' => 1, 'ended' => 0, 'skipped_gone' => false], app(DomainSubscriptionSync::class)->sync($this->organization));
    }

    public function test_start_is_the_expiration_anniversary_from_the_registration_on(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        // DENIC-Drift: Ablauf-Jahrestag zwei Tage vor dem Registrierungstag — erstes Lizenzjahr bleibt.
        $this->projection($connection, 'drift.de', ['customer_id' => $customer->id, 'registration_at' => '2016-01-25', 'expiration_at' => '2027-01-23']);
        // .de im ersten Jahr: der Gateway meldet Registrierung + 1 Monat als Ablauf, finalisiert aber am
        // Registrierungs-Jahrestag — der zählt (bewegung-beispiel.de, Prod 2026-10-29: ADD_DOMAIN 03.11.2025, Verlängerung 27.10.2026).
        $this->projection($connection, 'erstes-jahr.de', ['customer_id' => $customer->id, 'registration_at' => '2025-11-03', 'expiration_at' => '2025-12-03', 'finalization_at' => '2026-11-03']);
        // gTLD: Finalisierung 44 Tage nach dem Ablauf ist die Gnadenfrist der Registry, der Ablauf bleibt das Laufzeitende.
        $this->projection($connection, 'gnadenfrist.com', ['customer_id' => $customer->id, 'registration_at' => '2024-06-08', 'expiration_at' => '2027-06-08', 'finalization_at' => '2027-07-22']);
        // gTLD-Transfer im April, Registry-Zyklus 25.11.: die Jahre vor dem Transfer gehören dem alten Anbieter.
        $this->projection($connection, 'transfer.one', ['customer_id' => $customer->id, 'registration_at' => '2026-04-08', 'expiration_at' => '2027-11-25']);
        $this->projection($connection, 'ohne-ablauf.de', ['customer_id' => $customer->id, 'registration_at' => '2024-05-05', 'expiration_at' => null]);

        app(DomainSubscriptionSync::class)->sync($this->organization);

        $starts = static fn (string $domain): array => ResaleSubscription::query()->where('external_id', $domain)->firstOrFail()->periods()->orderBy('starts_on')->pluck('starts_on')->map(static fn ($d) => $d->toDateString())->all();
        $this->assertSame('2016-01-23', ResaleSubscription::query()->where('external_id', 'drift.de')->value('starts_on')?->toDateString());
        $this->assertSame(['2025-11-03', '2026-11-03'], $starts('erstes-jahr.de'), 'Registrierungs-Jahrestag, nicht der gemeldete Ablauf einen Monat nach der Registrierung');
        $this->assertSame('2024-06-08', ResaleSubscription::query()->where('external_id', 'gnadenfrist.com')->value('starts_on')?->toDateString(), 'Gnadenfrist verschiebt den Zyklus nicht');
        $this->assertSame(['2026-11-25'], $starts('transfer.one'), 'erster Jahrestag nach dem Transfer, nicht der Transfertag');
        $this->assertSame('2024-05-05', ResaleSubscription::query()->where('external_id', 'ohne-ablauf.de')->value('starts_on')?->toDateString());
    }

    public function test_moving_start_keeps_decided_periods_without_duplicates(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        // Bestand auf dem Registrierungstag (Perioden 25.01.), eine Periode entschieden …
        $projection = $this->projection($connection, 'drift-muster.de', ['customer_id' => $customer->id, 'registration_at' => '2016-01-25', 'expiration_at' => '2027-01-25']);
        app(DomainSubscriptionSync::class)->sync($this->organization);
        $subscription = ResaleSubscription::query()->where('external_id', 'drift-muster.de')->firstOrFail();
        $this->assertSame(11, $subscription->periods()->count());
        $subscription->periods()->whereDate('starts_on', '2025-01-25')->update(['status' => 'billed', 'decided_at' => now()]);

        // … dann liefert das Portal den gedrifteten Ablauf: Beginn rückt auf den 23.01., keine zweite Periode je Jahr.
        $projection->forceFill(['expiration_at' => '2027-01-23'])->save();
        app(DomainSubscriptionSync::class)->sync($this->organization);
        $subscription->refresh();
        $this->assertSame('2016-01-23', $subscription->starts_on->toDateString());
        $dates = $subscription->periods()->orderBy('starts_on')->pluck('starts_on')->map(static fn ($d) => $d->toDateString())->all();
        $this->assertCount(11, $dates);
        $this->assertContains('2025-01-25', $dates, 'entschiedene Periode behält ihr Datum');
        $this->assertContains('2026-01-23', $dates);
        $this->assertNotContains('2025-01-23', $dates, 'kein Duplikat neben der entschiedenen Periode');
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
        // Review 2026-09-10 (A6): Verkaufspreis je TLD aus dem Katalog (UVP), Artikelbezug „Domain .de"; seit 2026-10-30 folgt der Preis dem Katalog, gepflegt bleibt nur, was keine Katalogzeile hat.
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
        $this->projection($connection, 'badhaus-baeder.de', ['customer_id' => $customer->id]);
        $this->projection($connection, 'shop.example.co.uk', ['customer_id' => $customer->id]);
        $this->projection($connection, 'beispiel.com', ['customer_id' => $customer->id]);
        $this->projection($connection, 'zweideutig.net', ['customer_id' => $customer->id]);
        $this->projection($connection, 'manuell.de', ['customer_id' => $customer->id]);
        ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'manuell.de', 'label' => 'manuell.de',
            'customer_id' => $customer->id, 'quantity' => 1, 'starts_on' => '2023-03-15', 'currency' => 'EUR', 'status' => 'active', 'sale_unit_price' => '99.00',
        ]);

        app(DomainSubscriptionSync::class)->sync($this->organization);

        $de = ResaleSubscription::query()->where('external_id', 'badhaus-baeder.de')->firstOrFail();
        $this->assertSame('15.0000', $de->sale_unit_price?->getAmount(), 'UVP der Katalogzeile .de');
        $this->assertSame('lex:' . $article->id, $de->article_ref, 'Artikel „Domain .de"');
        $this->assertSame('15.00', $de->periods()->first()?->expected_sale?->getAmount(), 'Perioden tragen den Verkauf');

        $uk = ResaleSubscription::query()->where('external_id', 'shop.example.co.uk')->firstOrFail();
        $this->assertSame('12.0000', $uk->sale_unit_price?->getAmount(), 'Katalog „co.uk" ohne UVP → Katalogpreis; längster Suffix zuerst');

        $com = ResaleSubscription::query()->where('external_id', 'beispiel.com')->firstOrFail();
        $this->assertSame('18.0000', $com->sale_unit_price?->getAmount(), 'Katalog .com gilt erst 2027 → Artikelpreis 1,50 €/Monat × 12');
        $this->assertNotNull($com->article_ref, 'Artikel „.com-Domain"');

        $net = ResaleSubscription::query()->where('external_id', 'zweideutig.net')->firstOrFail();
        $this->assertNull($net->article_ref, 'zwei passende Artikel → keiner');
        $this->assertNull($net->sale_unit_price);

        $manual = ResaleSubscription::query()->where('external_id', 'manuell.de')->firstOrFail();
        $this->assertSame('15.0000', $manual->sale_unit_price?->getAmount(), 'der Preis folgt dem Katalog (Entscheidung 2026-10-30), auch über einen gepflegten Wert');

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
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH']);
        $end = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Zahlenwerk']);
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

    public function test_domain_price_follows_the_price_class_of_the_subuser_or_the_direct_class(): void {
        // Entscheidung 2026-10-30: Benutzerklassen des Portals sind Preisstaffeln — Subuser-Domains nehmen die Klasse
        // des Subusers, Domains im eigenen Konto die für direkte Kunden benannte, sonst die Zeile ohne Staffel.
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $owner = DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_user' => 'konto', 'depth' => 0, 'user_class' => 'GOLD', 'customer_id' => null]);
        $sub = DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_user' => 'sub-standard', 'depth' => 1, 'user_class' => 'STANDARD', 'customer_id' => $customer->id]);
        foreach ([['STANDARD', '8.10'], ['RESELLER', '6.30'], ['', '15.00']] as [$class, $list]) {
            ResalePriceEntry::query()->create([
                'organization_id' => $this->organization->id, 'provider' => SubscriptionProvider::DomainReselling, 'product' => '.de', 'price_class' => $class, 'term_months' => 12, 'interval' => 'yearly',
                'valid_from' => '2026-01-01', 'purchase_unit_price' => '4.50', 'list_unit_price' => $list, 'currency' => 'EUR',
            ]);
        }
        $this->projection($connection, 'sub-domain.de', ['customer_id' => $customer->id, 'reseller_account_id' => $sub->id, 'renewal_price' => null]);
        $this->projection($connection, 'direkt.de', ['customer_id' => $customer->id, 'reseller_account_id' => $owner->id]);

        app(DomainSubscriptionSync::class)->sync($this->organization);

        $subDomain = ResaleSubscription::query()->where('external_id', 'sub-domain.de')->firstOrFail();
        $this->assertSame('8.1000', $subDomain->sale_unit_price?->getAmount(), 'Klasse des Subusers');
        $this->assertSame('4.5000', $subDomain->purchase_unit_price?->getAmount(), 'Einkauf aus dem Katalog, wenn die Projektion keinen Preis nennt');
        $direct = ResaleSubscription::query()->where('external_id', 'direkt.de')->firstOrFail();
        $this->assertSame('15.0000', $direct->sale_unit_price?->getAmount(), 'ohne benannte Staffel die Zeile ohne Staffel');

        Setting::set(DomainSubscriptionSync::SETTING_DIRECT_PRICE_CLASS, 'reseller', SettingScope::Organization, $this->organization, null);
        $this->assertSame('RESELLER', DomainSubscriptionSync::directPriceClass($this->organization), 'Klasse groß geschrieben');
        app(DomainSubscriptionSync::class)->sync($this->organization);
        $this->assertSame('6.3000', $direct->fresh()?->sale_unit_price?->getAmount(), 'benannte Staffel für direkte Kunden, der Preis folgt dem Katalog');
        $this->assertSame('8.1000', $subDomain->fresh()?->sale_unit_price?->getAmount());
        $this->assertSame(['RESELLER', 'STANDARD'], DomainSubscriptionSync::knownPriceClasses($this->organization));
    }
}
