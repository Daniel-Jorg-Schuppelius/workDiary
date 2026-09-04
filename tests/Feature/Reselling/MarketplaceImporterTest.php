<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplaceImporterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{CompanyMappingMode, SubscriptionProvider, SubscriptionStatus};
use App\Models\{Customer, ForeignCustomer, LexofficeArticle};
use App\Models\Reselling\{CompanyMapping, ResaleImport, ResalePriceEntry, ResaleSubscription};
use App\Services\Reselling\Register\MarketplaceImporter;
use App\Support\XlsxExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use Tests\Unit\Reselling\QualityHostingContractsReaderTest;

/**
 * Anbieter-Exporte ins Reselling-Register (Feature 152, MVP-759): Upsert,
 * Ablösung, Halter aus dem Bestand, Lexoffice-Artikel als Preisquelle,
 * Inbox-Zuordnung mit Gedächtnis, Preiskatalog.
 */
class MarketplaceImporterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake(ResaleImport::DISK);
        $this->travelTo('2026-09-04');
    }

    protected function tearDown(): void {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /**
     * @return array<string, array{name: string, path: string}>
     */
    private function files(bool $withPriceList = false): array {
        $qh = QualityHostingContractsReaderTest::writeFixture();
        $this->tempFiles[] = $qh;
        $files = [
            ResaleImport::KIND_PURCHASES => ['name' => 'purchases.csv', 'path' => MarketplaceReconcilerTest::FIXTURE],
            ResaleImport::KIND_CONTRACTS => ['name' => 'Export.xlsx', 'path' => $qh],
        ];
        if ($withPriceList) {
            $prices = sys_get_temp_dir() . '/qh-prices-' . uniqid() . '.xlsx';
            file_put_contents($prices, XlsxExport::toStringMultiSheet([
                ['title' => 'Deckblatt', 'headers' => ['Preisliste für Reseller', ''], 'rows' => [['Reseller', '95229'], ['Gültigkeit ab', '01.09.2026']]],
                ['title' => 'Preisdaten', 'headers' => ['Produkttarif', 'Vertragslaufzeit in Monaten', 'Zahlungsintervall', 'Gültig ab', 'Preis pro Monat', 'Hersteller-UVP pro Monat', 'Preis pro Zahlungsintervall', 'Hersteller-UVP pro Zahlungsintervall', 'Hersteller-Key', 'Offer-Key'], 'rows' => [
                    ['Microsoft 365 Business Premium', 12, 'jährlich', null, 15.66, 19.06, 187.92, 228.72, 'K:1', 'O-12M'],
                    ['Exchange Online Plan 1', 12, 'jährlich', null, 2.86, 3.5, 34.32, 42, 'K:2', 'O-EXO'],
                ]],
            ]));
            $this->tempFiles[] = $prices;
            $files[ResaleImport::KIND_PRICELIST] = ['name' => 'Preisliste.xlsx', 'path' => $prices];
        }

        return $files;
    }

    public function test_import_upserts_subscriptions_links_successions_and_resolves_holders(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Muster Bau GmbH']);
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH']);
        $foreign = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Beispiel Logistik']);
        LexofficeArticle::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'art-bp',
            'name' => 'Microsoft 365 Business Premium',
            'article_number' => 'DCF-IT-MCLD-O001BP',
            'type' => 'SERVICE',
            'unit_name' => 'Monat',
            'net_unit_price' => '20.60',
            'currency' => 'EUR',
            'vat_rate' => '19',
            'synced_at' => now(),
        ]);

        $records = app(MarketplaceImporter::class)->import($this->organization, $this->orgAdmin(), $this->files(true));
        $this->assertCount(3, $records);
        foreach ($records as $record) {
            $this->assertSame(\App\Enums\Reselling\ImportStatus::Done, $record->status, (string) $record->error);
        }
        [$telekom, $qh, $prices] = $records;
        $this->assertSame(5, $telekom->rows_total, 'wöchentliche Zeile ist ein Befund, keine Position');
        $this->assertSame(5, $telekom->rows_created);
        $this->assertSame(3, $qh->rows_created);
        $this->assertSame(2, $prices->rows_created);

        // Ablösung: Telekom-Premium (8 Stück) → Quality-Hosting-Vertrag am Jahrestag.
        $old = ResaleSubscription::query()->where('external_id', 'ent-0001')->firstOrFail();
        $new = ResaleSubscription::query()->where('external_id', 'CNLCON00167')->firstOrFail();
        $this->assertSame(SubscriptionProvider::TelekomMarketplace, $old->provider);
        $this->assertSame(SubscriptionStatus::Superseded, $old->status);
        $this->assertSame($new->id, $old->successor_id);
        $this->assertSame('2025-08-02', $old->ends_on?->toDateString(), 'Telekom-Laufzeit am QH-Start gekappt');
        $this->assertSame(8, $old->quantity, 'Menge aus der Gebühr: 1.958,07 = 8 × 244,76');
        $this->assertSame('244.7600', $old->purchase_unit_price?->getAmount());
        $this->assertSame(SubscriptionStatus::Active, $new->status);
        $this->assertSame('187.9200', $new->purchase_unit_price?->getAmount());

        // Halter: Kunde per Namen, Fremdkunde per Namen, Rest in die Inbox.
        $this->assertSame($customer->id, $old->customer_id);
        $this->assertSame($customer->id, $new->customer_id);
        $exchange = ResaleSubscription::query()->where('external_id', 'ent-0003')->firstOrFail();
        $this->assertSame($foreign->id, $exchange->foreign_customer_id);
        $this->assertSame($partner->id, $exchange->billedTo()?->id);
        $this->assertSame(1, ResaleSubscription::query()->unassigned()->where('company_name', 'Unbekannt UG')->count(), 'die wöchentliche Zeile ist ein Befund');
        $this->assertSame(1, ResaleSubscription::query()->unassigned()->where('company_name', 'Alt AG')->count());
        $this->assertSame(1, $telekom->rows_unassigned);
        $this->assertSame(1, $qh->rows_unassigned);

        // Lexoffice-Artikel liefert Produkt und Verkaufspreis (20,60 € × 12).
        $this->assertNotNull($new->lexoffice_article_id);
        $this->assertSame('247.2000', $new->sale_unit_price?->getAmount());
        $this->assertSame('Microsoft 365 Business Premium', $new->productLabel() ? explode(' · ', $new->productLabel())[1] : null);
        $this->assertGreaterThan(0, $new->periods()->count(), 'Perioden geplant');
        $this->assertSame('1977.60', $new->periods()->first()?->expected_sale?->getAmount(), '8 × 247,20 €');

        // Preiskatalog
        $this->assertSame(2, ResalePriceEntry::query()->count());
        $premium = ResalePriceEntry::query()->where('product', 'Microsoft 365 Business Premium')->firstOrFail();
        $this->assertSame('2026-09-01', $premium->valid_from->toDateString());
        $this->assertSame('187.9200', $premium->purchase_unit_price->getAmount());
        $this->assertSame('228.7200', $premium->list_unit_price?->getAmount());

        // Zweiter Import: nichts doppelt, alles unverändert, Halterentscheidung bleibt.
        $unknown = ResaleSubscription::query()->where('company_name', 'Unbekannt UG')->first();
        $this->assertNotNull($unknown);
        $unknown->forceFill(['is_own_holding' => true])->save();
        $again = app(MarketplaceImporter::class)->import($this->organization, null, $this->files());
        $this->assertSame(8, ResaleSubscription::query()->count());
        $this->assertSame(5, $again[0]->rows_unchanged);
        $this->assertSame(0, $again[0]->rows_created);
        $this->assertTrue($unknown->fresh()?->is_own_holding, 'manuelle Halterentscheidung überlebt den Import');
    }

    public function test_stored_partner_mapping_creates_the_end_customer_on_import(): void {
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH']);
        CompanyMapping::create([
            'organization_id' => $this->organization->id,
            'company_key' => '100003',
            'company_name' => 'Unbekannt UG',
            'mode' => CompanyMappingMode::Partner,
            'customer_id' => $partner->id,
        ]);

        app(MarketplaceImporter::class)->import($this->organization, null, $this->files());

        $foreign = ForeignCustomer::query()->where('customer_id', $partner->id)->where('name', 'Unbekannt UG')->first();
        $this->assertNotNull($foreign, 'Fremdkunde unter dem Partner angelegt');
        $this->assertSame(1, ResaleSubscription::query()->where('foreign_customer_id', $foreign->id)->count());
    }

    public function test_inbox_assignment_updates_all_subscriptions_and_is_remembered(): void {
        $admin = $this->orgAdmin();
        app(MarketplaceImporter::class)->import($this->organization, $admin, $this->files());
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH']);

        $this->actingAs($admin)->get(route('finance.resale.inbox'))
            ->assertOk()
            ->assertSee('Unbekannt UG')
            ->assertSee('Alt AG');
        $this->actingAs($admin)->get(route('finance.resale.inbox.assign', ['company' => 'Unbekannt UG']))->assertOk()->assertSee('Unbekannt UG');

        $this->actingAs($admin)->post(route('finance.resale.inbox.store'), [
            'company' => 'Unbekannt UG',
            'mode' => 'partner',
            'customer_id' => $partner->sqid,
        ])->assertRedirect(route('finance.resale.inbox'));

        $foreign = ForeignCustomer::query()->where('customer_id', $partner->id)->where('name', 'Unbekannt UG')->firstOrFail();
        $this->assertSame(1, ResaleSubscription::query()->where('foreign_customer_id', $foreign->id)->count());
        $this->assertSame(0, ResaleSubscription::query()->unassigned()->where('company_name', 'Unbekannt UG')->count());
        $mapping = CompanyMapping::query()->where('normalized_name', 'unbekannt ug')->firstOrFail();
        $this->assertSame(CompanyMappingMode::Partner, $mapping->mode);
        $this->assertSame($partner->id, $mapping->customer_id);

        // Eigener Bestand für Alt AG
        $this->actingAs($admin)->post(route('finance.resale.inbox.store'), ['company' => 'Alt AG', 'mode' => 'own'])->assertRedirect(route('finance.resale.inbox'));
        $this->assertSame(0, ResaleSubscription::query()->unassigned()->whereIn('company_name', ['Unbekannt UG', 'Alt AG'])->count());
        $this->assertTrue(ResaleSubscription::query()->where('company_name', 'Alt AG')->value('is_own_holding'));
        // Muster Bau und Beispiel Logistik haben in diesem Test keinen Kunden → bleiben in der Inbox.
        $this->actingAs($admin)->get(route('finance.resale.inbox'))->assertOk()->assertDontSee('Unbekannt UG')->assertSee('Muster Bau GmbH');
    }

    public function test_import_dialog_stores_files_and_reports_counts(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.import.create'))->assertOk()->assertSee(__('resale.import.title'));
        $qh = QualityHostingContractsReaderTest::writeFixture();
        $this->tempFiles[] = $qh;

        $response = $this->actingAs($admin)->post(route('finance.resale.import.store'), [
            'telekom' => UploadedFile::fake()->createWithContent('purchases.csv', (string) file_get_contents(MarketplaceReconcilerTest::FIXTURE)),
            'qualityhosting' => new UploadedFile($qh, 'Export.xlsx', null, null, true),
        ]);
        $response->assertRedirect(route('finance.resale.inbox'))->assertSessionHas('success');
        $this->assertSame(2, ResaleImport::query()->count());
        $this->assertSame(8, ResaleSubscription::query()->count());
        Storage::disk(ResaleImport::DISK)->assertExists((string) ResaleImport::query()->first()?->file_path);

        $this->actingAs($admin)->post(route('finance.resale.import.store'), [])->assertRedirect(route('finance.resale.index'))->assertSessionHas('error');
    }

    public function test_console_import_command(): void {
        $qh = QualityHostingContractsReaderTest::writeFixture();
        $this->tempFiles[] = $qh;
        $this->artisan('resale:import', ['--org' => $this->organization->id, '--telekom' => MarketplaceReconcilerTest::FIXTURE, '--qualityhosting' => $qh])
            ->expectsOutputToContain('Telekom-Käufe: 5 Zeilen')
            ->assertSuccessful();
        $this->assertSame(8, ResaleSubscription::query()->count());
        $this->artisan('resale:import', ['--org' => $this->organization->id])->assertFailed();
    }
}
