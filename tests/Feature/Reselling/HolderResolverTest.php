<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HolderResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\CompanyMappingMode;
use App\Models\{Customer, ForeignCustomer, Organization};
use App\Models\Reselling\CompanyMapping;
use App\Services\Reselling\Marketplace\MarketplaceCompany;
use App\Services\Reselling\Register\HolderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Halter einer importierten Firma (Feature 152, MVP-759, Review 2026-09-10 G):
 * Vorschläge für die Inbox, gespeicherte Zuordnung vor Fremdkunde vor Kunde,
 * Kundennummer vor Name, Eindeutigkeit, Lauf-Cache mit `reset()`.
 */
class HolderResolverTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function customer(string $name, array $attributes = []): Customer {
        // company explizit: die Factory würde einen zufälligen Firmennamen setzen, der zufällig treffen könnte.
        return Customer::factory()->create(array_merge(['organization_id' => $this->organization->id, 'name' => $name, 'company' => $name], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function foreign(Customer $partner, string $name, array $attributes = []): ForeignCustomer {
        return ForeignCustomer::factory()->create(array_merge(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => $name, 'company' => $name], $attributes));
    }

    private function company(string $name, string $key = '100001', ?string $partnerNumber = null): MarketplaceCompany {
        return new MarketplaceCompany($key, $name, null, null, $partnerNumber);
    }

    public function test_suggestions_list_similar_customers_and_end_customers_only(): void {
        $klimpel = $this->customer('Klimpel Bäder GmbH');
        $byCompany = $this->customer('Herr Peter Urban', ['company' => 'Urban Fliesen GmbH']);
        $this->customer('Auerswald GmbH & Co. KG');
        $partner = $this->customer('LDS Systems GmbH');
        $kaik = $this->foreign($partner, 'Steuerbüro Kaik');
        $this->foreign($partner, 'Kaik Immobilien', ['archived_at' => now()]);
        $otherOrg = Organization::factory()->create();
        Customer::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Klimpel Bäder GmbH', 'company' => 'Klimpel Bäder GmbH']);

        $resolver = new HolderResolver;
        // Exakt normalisiert („Baeder" = „Bäder") und tokenbasiert (Rechtsform zählt nicht, Reihenfolge egal).
        $result = $resolver->suggestions($this->organization, 'KLIMPEL BAEDER');
        $this->assertSame([$klimpel->id], $result['customers']->pluck('id')->all(), 'nur der eigene Mandant');
        $this->assertTrue($result['foreign']->isEmpty());

        $result = $resolver->suggestions($this->organization, 'Fliesen Urban');
        $this->assertSame([$byCompany->id], $result['customers']->pluck('id')->all(), 'Treffer über die Firma, nicht den Namen');
        $this->assertSame('Herr Peter Urban', $result['customers'][0]->name);

        $result = $resolver->suggestions($this->organization, 'Kaik');
        $this->assertTrue($result['customers']->isEmpty());
        $this->assertSame([$kaik->id], $result['foreign']->pluck('id')->all(), 'archivierte Fremdkunden werden nicht vorgeschlagen');
        $this->assertSame('LDS Systems GmbH', $result['foreign'][0]->customer?->name, 'Partner ist geladen (Anzeige in der Inbox)');

        // Kurze Kürzel treffen nichts (kein Kern-Token ≥ 4 Zeichen), Unbekanntes ebenso wenig.
        $this->assertTrue($resolver->suggestions($this->organization, 'GSR')['customers']->isEmpty());
        $this->assertTrue($resolver->suggestions($this->organization, 'Völlig Unbekannt AG')['customers']->isEmpty());
        $this->assertTrue($resolver->suggestions($this->organization, '')['customers']->isEmpty());
    }

    public function test_stored_mapping_wins_over_matching_and_covers_own_customer_and_partner(): void {
        $klimpel = $this->customer('Klimpel Bäder GmbH');
        $other = $this->customer('Ganz Andere GmbH');
        $partner = $this->customer('LDS Systems GmbH');
        $this->foreign($partner, 'Klimpel Bäder GmbH');
        $resolver = new HolderResolver;

        // Ohne Zuordnung: der Fremdkunde gewinnt vor dem gleichnamigen Kunden.
        $this->assertSame(HolderResolver::SOURCE_FOREIGN, $resolver->resolve($this->organization, $this->company('Klimpel Bäder GmbH'))['source'] ?? null);

        // customer:<Sqid> — gespeichert schlägt Fremdkunde und Namensgleichheit.
        $stored = ['100001' => 'customer:' . $other->sqid];
        $resolved = $resolver->resolve($this->organization, $this->company('Klimpel Bäder GmbH'), $stored);
        $this->assertSame(['customer_id' => $other->id, 'foreign_customer_id' => null, 'is_own_holding' => false, 'source' => HolderResolver::SOURCE_STORED], $resolved);

        // Unter dem normalisierten Namen gespeichert (kein Schlüssel) — greift ebenso.
        $stored = ['klimpel baeder gmbh' => 'customer:' . $klimpel->sqid];
        $this->assertSame($klimpel->id, $resolver->resolve($this->organization, $this->company('Klimpel Bäder GmbH', 'CNL00007'), $stored)['customer_id'] ?? null);

        // own — kein Halter, aber entschieden.
        $resolved = $resolver->resolve($this->organization, $this->company('Eigene Lizenzen'), ['eigene lizenzen' => CompanyMapping::TARGET_OWN]);
        $this->assertSame(['customer_id' => null, 'foreign_customer_id' => null, 'is_own_holding' => true, 'source' => HolderResolver::SOURCE_STORED], $resolved);

        // partner:<Sqid> — Fremdkunde unter dem Partner, neu angelegt und beim zweiten Mal (tokenähnlich) wiedergefunden.
        $this->assertSame(0, ForeignCustomer::query()->where('name', 'Steuerbüro Kaik')->count());
        $resolved = $resolver->resolve($this->organization, $this->company('Steuerbüro Kaik', '100009'), ['100009' => 'partner:' . $partner->sqid]);
        $created = ForeignCustomer::query()->where('customer_id', $partner->id)->where('name', 'Steuerbüro Kaik')->firstOrFail();
        $this->assertSame(['customer_id' => null, 'foreign_customer_id' => $created->id, 'is_own_holding' => false, 'source' => HolderResolver::SOURCE_STORED], $resolved);
        $this->assertSame('Steuerbüro Kaik', $created->company);
        $again = $resolver->resolve($this->organization, $this->company('Kaik Steuerbüro GmbH', '100009'), ['100009' => 'partner:' . $partner->sqid]);
        $this->assertSame($created->id, $again['foreign_customer_id'] ?? null, 'kein zweiter Fremdkunde für die Schreibvariante');
        $this->assertSame(2, ForeignCustomer::query()->where('customer_id', $partner->id)->count(), 'Klimpel (Fixture) + Kaik');

        // Ziele, die im Register nichts bedeuten, fallen auf die Erkennung zurück: Lexoffice-Kontakt-UUID,
        // unbekannte Sqid, Kunde eines anderen Mandanten.
        $foreignOrg = Organization::factory()->create();
        $stranger = Customer::factory()->create(['organization_id' => $foreignOrg->id, 'name' => 'Fremd GmbH', 'company' => 'Fremd GmbH']);
        foreach (['0f8b6a2e-1c0d-4e3a-9b1a-2c5d6e7f8a9b', 'customer:nope', 'customer:' . $stranger->sqid, 'partner:' . $stranger->sqid] as $target) {
            $resolved = $resolver->resolve($this->organization, $this->company('Ganz Andere GmbH', '100002'), ['100002' => $target]);
            $this->assertSame(HolderResolver::SOURCE_CUSTOMER, $resolved['source'] ?? null, $target);
            $this->assertSame($other->id, $resolved['customer_id'] ?? null, $target);
        }
        $this->assertSame(0, ForeignCustomer::query()->where('organization_id', $foreignOrg->id)->count(), 'nichts beim fremden Mandanten angelegt');
    }

    public function test_customer_matching_prefers_the_partner_customer_number_and_requires_uniqueness(): void {
        $byNumber = $this->customer('Beispiel Logistik GmbH', ['number' => '10031']);
        $byName = $this->customer('Muster Bau GmbH');
        $this->customer('Muster Bau GmbH', ['number' => '10099']);
        $resolver = new HolderResolver;

        // Kundennummer schlägt den Namen — auch wenn der Name auf einen anderen Kunden zeigt.
        $resolved = $resolver->resolve($this->organization, $this->company('Muster Bau GmbH', 'CNL00009', '10031'));
        $this->assertSame(['customer_id' => $byNumber->id, 'foreign_customer_id' => null, 'is_own_holding' => false, 'source' => HolderResolver::SOURCE_CUSTOMER], $resolved);

        // Unbekannte Nummer: Name als Rückfall — hier zweideutig (zwei „Muster Bau GmbH") → Inbox.
        $this->assertNull($resolver->resolve($this->organization, $this->company('Muster Bau GmbH', 'CNL00010', '99999')));
        $this->assertNull($resolver->resolve($this->organization, $this->company('Muster Bau GmbH', 'CNL00010')));

        // Eindeutiger Name (normalisiert): nur beispiel logistik.
        $this->assertSame($byNumber->id, $resolver->resolve($this->organization, $this->company(' beispiel-logistik gmbh '))['customer_id'] ?? null);
        // Der Kunde ist nur tokenähnlich („Muster Bau" ohne GmbH): keine Fuzzy-Erkennung beim Kunden — die gibt es nur als Vorschlag.
        $this->assertNull($resolver->resolve($this->organization, $this->company('Muster Bau Berlin')));
        $suggested = (new HolderResolver)->suggestions($this->organization, 'Muster Bau Berlin')['customers']->pluck('id')->sort()->values()->all();
        $this->assertSame(2, count($suggested));
        $this->assertContains($byName->id, $suggested);
    }

    public function test_end_customer_matching_needs_one_partner_and_prefers_exact_names(): void {
        $lds = $this->customer('LDS Systems GmbH');
        $other = $this->customer('Zweiter Partner GmbH');
        $exact = $this->foreign($lds, 'Steuerbüro Kaik');
        $this->foreign($lds, 'Kaik Immobilien GmbH');
        $resolver = new HolderResolver;

        // Exakter Name schlägt Token-Treffer desselben Partners.
        $this->assertSame($exact->id, $resolver->resolve($this->organization, $this->company('Steuerbüro Kaik'))['foreign_customer_id'] ?? null);
        // Nur Token-Treffer, mehrere beim selben Partner: der erste.
        $resolved = $resolver->resolve($this->organization, $this->company('Kaik'));
        $this->assertSame(HolderResolver::SOURCE_FOREIGN, $resolved['source'] ?? null);
        $this->assertSame($exact->id, $resolved['foreign_customer_id'] ?? null);

        // Gleicher Name bei zwei Partnern: nicht eindeutig → kein Halter (Inbox).
        $this->foreign($other, 'Steuerbüro Kaik');
        $resolver->reset();
        $this->assertNull($resolver->resolve($this->organization, $this->company('Steuerbüro Kaik')));
        // Archivierte Fremdkunden zählen nicht — damit ist es wieder eindeutig.
        ForeignCustomer::query()->where('customer_id', $other->id)->update(['archived_at' => now()]);
        $resolver->reset();
        $this->assertSame($exact->id, $resolver->resolve($this->organization, $this->company('Steuerbüro Kaik'))['foreign_customer_id'] ?? null);
    }

    public function test_run_cache_sees_new_master_data_only_after_reset(): void {
        $resolver = new HolderResolver;
        $company = $this->company('Neu Angelegt GmbH');
        $this->assertNull($resolver->resolve($this->organization, $company), 'noch kein Kunde');

        // Kunde nach dem ersten Lauf angelegt: der Lauf-Cache kennt ihn nicht …
        $customer = $this->customer('Neu Angelegt GmbH');
        $this->assertNull($resolver->resolve($this->organization, $company), 'Lauf-Cache: Kunden werden je Lauf einmal geladen');
        // … bis reset().
        $resolver->reset();
        $this->assertSame($customer->id, $resolver->resolve($this->organization, $company)['customer_id'] ?? null);

        // Fremdkunden ebenso — außer der Resolver legt ihn selbst an (partner:-Ziel), dann ist er sofort sichtbar.
        $partner = $this->customer('LDS Systems GmbH');
        $foreign = $this->foreign($partner, 'Neu Angelegt GmbH');
        $this->assertSame(HolderResolver::SOURCE_CUSTOMER, $resolver->resolve($this->organization, $company)['source'] ?? null, 'Fremdkunden-Cache aus dem letzten Lauf');
        $resolver->reset();
        $this->assertSame($foreign->id, $resolver->resolve($this->organization, $company)['foreign_customer_id'] ?? null, 'nach reset() gewinnt der Fremdkunde');

        $mapped = $resolver->resolve($this->organization, $this->company('Haus 24 GmbH', '100009'), ['100009' => 'partner:' . $partner->sqid]);
        $this->assertSame($mapped['foreign_customer_id'] ?? null, $resolver->resolve($this->organization, $this->company('Haus 24 GmbH', '100010'))['foreign_customer_id'] ?? null, 'selbst angelegter Fremdkunde ohne reset() sichtbar');

        // Eine gespeicherte Zuordnung aus der Tabelle ist der übliche Weg, „$stored" zu füllen.
        CompanyMapping::create(['organization_id' => $this->organization->id, 'company_key' => '100011', 'company_name' => 'Gemerkt UG', 'mode' => CompanyMappingMode::Customer, 'customer_id' => $customer->id]);
        $this->assertSame($customer->id, $resolver->resolve($this->organization, $this->company('Gemerkt UG', '100011'), CompanyMapping::targetsFor($this->organization))['customer_id'] ?? null);
    }
}
