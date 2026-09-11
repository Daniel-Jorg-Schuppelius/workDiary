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

    public const FIXTURE = __DIR__ . '/../../Fixtures/Reselling/marketplace-purchases.csv';

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
            ResaleImport::KIND_PURCHASES => ['name' => 'purchases.csv', 'path' => self::FIXTURE],
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
        // Die Firma ist aus der Zuordnungsliste raus; im Import-Protokoll darunter steht ihr Zeilenbefund weiterhin (Review 2026-09-10, A1).
        $this->actingAs($admin)->get(route('finance.resale.inbox'))->assertOk()
            ->assertDontSee(route('finance.resale.inbox.assign', ['company' => 'Unbekannt UG']), false)
            ->assertSee(route('finance.resale.inbox.assign', ['company' => 'Muster Bau GmbH']), false);
    }

    public function test_import_dialog_stores_files_and_reports_counts(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.import.create'))->assertOk()->assertSee(__('resale.import.title'));
        $qh = QualityHostingContractsReaderTest::writeFixture();
        $this->tempFiles[] = $qh;

        $response = $this->actingAs($admin)->post(route('finance.resale.import.store'), [
            'telekom' => UploadedFile::fake()->createWithContent('purchases.csv', (string) file_get_contents(self::FIXTURE)),
            'qualityhosting' => new UploadedFile($qh, 'Export.xlsx', null, null, true),
        ]);
        $response->assertRedirect(route('finance.resale.inbox'))->assertSessionHas('success');
        $this->assertSame(2, ResaleImport::query()->count());
        $this->assertSame(8, ResaleSubscription::query()->count());
        Storage::disk(ResaleImport::DISK)->assertExists((string) ResaleImport::query()->first()?->file_path);

        // Keine Datei: Feldfehler als 422 im Dialog statt Flash-Redirect (Review 2026-09-11), kein Lauf.
        $this->actingAs($admin)->postJson(route('finance.resale.import.store'), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.telekom.0', __('resale.import.flash.no_files'));
        $this->assertSame(2, ResaleImport::query()->count());
    }

    public function test_console_import_command(): void {
        $qh = QualityHostingContractsReaderTest::writeFixture();
        $this->tempFiles[] = $qh;
        $this->artisan('resale:import', ['--org' => $this->organization->id, '--telekom' => self::FIXTURE, '--qualityhosting' => $qh])
            ->expectsOutputToContain('Telekom-Käufe: 5 Zeilen')
            ->assertSuccessful();
        $this->assertSame(8, ResaleSubscription::query()->count());
        $this->artisan('resale:import', ['--org' => $this->organization->id])->assertFailed();
    }

    /**
     * Review 2026-09-10 (Own): „Eigener Bestand" wird als Zuordnung gemerkt —
     * eine später neu importierte Firma (neue Kennung) landet nicht mehr in
     * der Inbox, sondern direkt im eigenen Bestand.
     */
    public function test_own_holding_assignment_is_remembered_for_the_next_import(): void {
        $admin = $this->orgAdmin();
        app(MarketplaceImporter::class)->import($this->organization, $admin, $this->files());
        $this->assertSame(1, ResaleSubscription::query()->unassigned()->where('company_name', 'Unbekannt UG')->count());

        $this->actingAs($admin)->post(route('finance.resale.inbox.store'), ['company' => 'Unbekannt UG', 'mode' => 'own'])->assertRedirect(route('finance.resale.inbox'));
        $mapping = CompanyMapping::query()->where('normalized_name', 'unbekannt ug')->firstOrFail();
        $this->assertSame(CompanyMappingMode::Own, $mapping->mode);
        $this->assertNull($mapping->customer_id);
        $this->assertSame(CompanyMapping::TARGET_OWN, $mapping->target());
        $this->assertTrue(ResaleSubscription::query()->where('company_name', 'Unbekannt UG')->value('is_own_holding'));

        // Abo der Firma entfernen und erneut importieren: der Halter kommt aus der gemerkten Zuordnung.
        ResaleSubscription::query()->where('company_name', 'Unbekannt UG')->delete();
        app(MarketplaceImporter::class)->import($this->organization, null, $this->files());
        $again = ResaleSubscription::query()->where('company_name', 'Unbekannt UG')->firstOrFail();
        $this->assertTrue($again->is_own_holding, 'gemerkte Own-Zuordnung greift beim Re-Import');
        $this->assertNull($again->customer_id);
        $this->assertNull($again->foreign_customer_id);
        $this->assertSame(0, ResaleSubscription::query()->unassigned()->where('company_name', 'Unbekannt UG')->count());
        $this->actingAs($admin)->get(route('finance.resale.inbox'))->assertOk()
            ->assertDontSee(route('finance.resale.inbox.assign', ['company' => 'Unbekannt UG']), false);
    }

    /**
     * Vorgänger/Nachfolger der Ablösung im Fixture: Telekom ent-0001 (Muster Bau, 8 Lizenzen,
     * 02.08.2024–02.08.2026) → Quality Hosting CNLCON00167 (8 Lizenzen ab 02.08.2025).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function existingSubscription(string $provider, string $externalId, array $attributes = []): ResaleSubscription {
        return ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => $provider, 'external_id' => $externalId,
            'label' => 'Microsoft 365 Business Premium', 'company_name' => 'Muster Bau GmbH', 'quantity' => 8, 'starts_on' => '2024-08-02',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'currency' => 'EUR', 'status' => 'active',
        ], $attributes));
    }

    public function test_succession_passes_the_holder_from_predecessor_to_successor(): void {
        // Der Telekom-Vertrag hat einen manuell entschiedenen Halter, der nicht per Namen erkannt würde;
        // der neue QH-Vertrag kommt ohne Halter aus der Datei → erbt ihn über die Ablösung.
        $holder = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Anders GmbH', 'company' => 'Anders GmbH']);
        $old = $this->existingSubscription('telekom_marketplace', 'ent-0001', ['customer_id' => $holder->id]);
        $this->assertSame(0, ResaleSubscription::query()->where('external_id', 'CNLCON00167')->count());

        app(MarketplaceImporter::class)->import($this->organization, null, $this->files());

        $old->refresh();
        $new = ResaleSubscription::query()->where('external_id', 'CNLCON00167')->firstOrFail();
        $this->assertSame(SubscriptionStatus::Superseded, $old->status);
        $this->assertSame($new->id, $old->successor_id);
        $this->assertSame($holder->id, $old->customer_id, 'manueller Halter überlebt den Import');
        $this->assertSame($holder->id, $new->customer_id, 'Nachfolger erbt den Halter des Vorgängers');
        $this->assertFalse($new->is_own_holding);
        $this->assertTrue($new->hasHolder());
        // Die zweite Telekom-Position derselben Firma (ent-0002, kein Nachfolger) erbt nichts: sie wartet in der Inbox.
        $this->assertSame(['ent-0002'], ResaleSubscription::query()->unassigned()->where('company_name', 'Muster Bau GmbH')->pluck('external_id')->all());
    }

    public function test_succession_passes_the_holder_from_successor_to_predecessor(): void {
        // Umgekehrt: der QH-Vertrag ist schon zugeordnet, der alte Telekom-Vertrag kommt neu (z. B. Alt-Export) ohne Halter.
        $holder = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Anders GmbH', 'company' => 'Anders GmbH']);
        $new = $this->existingSubscription('qualityhosting', 'CNLCON00167', ['customer_id' => $holder->id, 'starts_on' => '2025-08-02']);

        app(MarketplaceImporter::class)->import($this->organization, null, $this->files());

        $old = ResaleSubscription::query()->where('external_id', 'ent-0001')->firstOrFail();
        $this->assertSame($new->id, $old->successor_id);
        $this->assertSame(SubscriptionStatus::Superseded, $old->status);
        $this->assertSame('2025-08-02', $old->ends_on?->toDateString(), 'Telekom-Laufzeit am QH-Start gekappt');
        $this->assertSame($holder->id, $old->customer_id, 'Vorgänger erbt den Halter des Nachfolgers');
        $this->assertSame($holder->id, $new->fresh()?->customer_id, 'Halter des Nachfolgers bleibt');
        // Ein zweiter Halter mit passendem Namen ändert daran nichts: der Halter ist entschieden.
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Muster Bau GmbH', 'company' => 'Muster Bau GmbH']);
        app(MarketplaceImporter::class)->import($this->organization, null, $this->files());
        $this->assertSame($holder->id, $old->fresh()?->customer_id);
        $this->assertSame($holder->id, $new->fresh()?->customer_id);
    }

    public function test_import_carries_two_assignments_to_the_same_holder_as_two_counterparts(): void {
        // Ablösung über den Import (nicht per Reflection): zwei Abtretungen des Vorgängers an denselben Halter
        // werden zwei Gegenstücke am Nachfolger — die Sammlung der vorhandenen Abtretungen wird vor der Schleife
        // einmal geladen, was dieser Lauf anlegt, wird nicht wieder zusammengefasst (Review 2026-09-10, H).
        $holder = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Anders GmbH', 'company' => 'Anders GmbH']);
        $tenant = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Mieter GmbH', 'company' => 'Mieter GmbH']);
        $old = $this->existingSubscription('telekom_marketplace', 'ent-0001', ['customer_id' => $holder->id, 'ends_on' => '2026-08-02', 'status' => 'cancelled', 'renewal' => 'cancel']);
        $first = $this->existingSubscription('telekom_marketplace', 'ent-0001#1', ['parent_id' => $old->id, 'customer_id' => $tenant->id, 'company_name' => 'Mieter GmbH', 'quantity' => 2, 'sale_unit_price' => '250.00', 'notes' => 'Büro 1']);
        $second = $this->existingSubscription('telekom_marketplace', 'ent-0001#2', ['parent_id' => $old->id, 'customer_id' => $tenant->id, 'company_name' => 'Mieter GmbH', 'quantity' => 1, 'starts_on' => '2025-01-01']);

        $records = app(MarketplaceImporter::class)->import($this->organization, null, $this->files());
        $this->assertSame(\App\Enums\Reselling\ImportStatus::Done, $records[0]->status, (string) $records[0]->error);

        $new = ResaleSubscription::query()->where('external_id', 'CNLCON00167')->firstOrFail();
        $this->assertSame($new->id, $old->fresh()?->successor_id);
        $carried = ResaleSubscription::query()->where('parent_id', $new->id)->orderBy('external_id')->get();
        $this->assertSame(['CNLCON00167#1', 'CNLCON00167#2'], $carried->pluck('external_id')->all(), 'zwei Gegenstücke, nicht eines mit Summe 3');
        $this->assertSame([2, 1], $carried->pluck('quantity')->all());
        $this->assertSame([$tenant->id, $tenant->id], $carried->pluck('customer_id')->all());
        $this->assertSame(['2025-08-02', '2025-08-02'], $carried->map(static fn(ResaleSubscription $s): string => $s->starts_on->toDateString())->all(), 'ab dem Beginn des Nachfolgers');
        $this->assertSame('250.0000', $carried[0]->sale_unit_price?->getAmount(), 'Verkaufspreis der Abtretung wandert mit');
        $this->assertSame('Büro 1', $carried[0]->notes);
        $this->assertSame('187.9200', $carried[0]->purchase_unit_price?->getAmount(), 'Einkauf vom Nachfolger');
        $this->assertSame('Mieter GmbH', $carried[1]->company_name);

        // Alte Abtretungen enden mit dem Vorgänger; Nachfolger plant mit dem Rest (8 − 3), Gegenstücke mit ihrer Menge.
        foreach ([$first, $second] as $assignment) {
            $this->assertSame('2025-08-01', $assignment->fresh()?->ends_on?->toDateString());
            $this->assertSame(SubscriptionStatus::Superseded, $assignment->fresh()?->status);
        }
        $this->assertSame([5, 5], $new->periods()->pluck('quantity')->all());
        $this->assertSame([2, 2], $carried[0]->periods()->pluck('quantity')->all());
        $this->assertSame([1, 1], $carried[1]->periods()->pluck('quantity')->all());
        $this->assertSame([6], $old->fresh()?->periods()->pluck('quantity')->all(), '2024: 8 − 2 (die zweite Abtretung beginnt erst 2025); 2025 gehört dem Nachfolger');

        // Zweiter Import: Ablösung steht schon, nichts wird erneut übertragen.
        app(MarketplaceImporter::class)->import($this->organization, null, $this->files());
        $this->assertSame(2, ResaleSubscription::query()->where('parent_id', $new->id)->count());
        $this->assertSame([2, 1], ResaleSubscription::query()->where('parent_id', $new->id)->orderBy('external_id')->pluck('quantity')->all());
    }

    public function test_changed_row_updates_the_subscription_but_keeps_a_manual_purchase_price(): void {
        app(MarketplaceImporter::class)->import($this->organization, null, $this->files());
        $subscription = ResaleSubscription::query()->where('external_id', 'ent-0002')->firstOrFail();
        $this->assertSame('2026-10-07', $subscription->ends_on?->toDateString());
        $this->assertSame('244.7600', $subscription->purchase_unit_price?->getAmount());
        $subscription->forceFill(['purchase_unit_price' => '200.0000'])->save();

        // Laufzeitende der Position im Export geändert → Hash anders → Zeile gilt als geändert.
        $changed = sys_get_temp_dir() . '/purchases-changed-' . uniqid() . '.csv';
        file_put_contents($changed, str_replace(',07.10.26,EUR,07.10.24,', ',07.10.27,EUR,07.10.24,', (string) file_get_contents(self::FIXTURE)));
        $this->tempFiles[] = $changed;
        $files = $this->files();
        $files[ResaleImport::KIND_PURCHASES] = ['name' => 'purchases.csv', 'path' => $changed];

        $records = app(MarketplaceImporter::class)->import($this->organization, null, $files);
        $this->assertSame(1, $records[0]->rows_updated, 'nur ent-0002');
        $this->assertSame(4, $records[0]->rows_unchanged);
        $subscription->refresh();
        $this->assertSame('2027-10-07', $subscription->ends_on?->toDateString(), 'Laufzeit folgt der Datei');
        $this->assertSame('200.0000', $subscription->purchase_unit_price?->getAmount(), 'manueller Einkaufspreis bleibt — er steht nicht im Hash');
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);

        // Unverändert erneut: Hash gleich, Preis bleibt.
        $again = app(MarketplaceImporter::class)->import($this->organization, null, $files);
        $this->assertSame(0, $again[0]->rows_updated);
        $this->assertSame('200.0000', $subscription->fresh()?->purchase_unit_price?->getAmount());
    }
}
