<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResellingReconciliationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Reselling\{CompanyMappingMode, ReconciliationRunStatus};
use App\Jobs\Reselling\RunReconciliationJob;
use App\Models\{Customer, ExternalReference, ForeignCustomer};
use App\Models\Reselling\{CompanyMapping, ReconciliationRun};
use App\Plugins\Lexoffice\{LexofficeInvoiceLineReader, LexofficePlugin};
use App\Services\Reselling\Marketplace\ReconciliationRunner;
use App\Support\XlsxExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Bus, Storage};
use Tests\Concerns\WithOrganization;
use Tests\Feature\Reselling\MarketplaceReconcilerTest;
use Tests\Support\InteractsWithPlugins;
use Tests\TestCase;
use Tests\Unit\Reselling\QualityHostingContractsReaderTest;

class ResellingReconciliationTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake(ReconciliationRun::DISK);
    }

    public function test_index_requires_the_reselling_permission(): void {
        $this->actingAs($this->orgUser())->get(route('finance.reselling.index'))->assertForbidden();

        $this->actingAs($this->orgAdmin())->get(route('finance.reselling.index'))
            ->assertOk()
            ->assertSee(__('reselling.title.index'))
            ->assertSee(__('reselling.empty.runs'));
    }

    public function test_store_saves_files_creates_run_and_dispatches_job(): void {
        Bus::fake();
        $admin = $this->orgAdmin();
        $xlsx = QualityHostingContractsReaderTest::writeFixture();

        $response = $this->actingAs($admin)->post(route('finance.reselling.store'), [
            'telekom' => UploadedFile::fake()->createWithContent('purchases.csv', (string) file_get_contents(MarketplaceReconcilerTest::FIXTURE)),
            'qualityhosting' => new UploadedFile($xlsx, 'Export.xlsx', null, null, true),
            'reference_date' => '2025-01-01',
            'window_before' => 30,
            'window_after' => 60,
        ]);
        @unlink($xlsx);

        $run = ReconciliationRun::query()->first();
        $this->assertInstanceOf(ReconciliationRun::class, $run);
        $response->assertRedirect(route('finance.reselling.show', $run->sqid));

        $this->assertSame(ReconciliationRunStatus::Queued, $run->status);
        $this->assertSame('2025-01-01', $run->reference_date->toDateString());
        $this->assertSame(30, $run->window_before);
        $this->assertSame(60, $run->window_after);
        $this->assertSame($admin->id, $run->created_by_user_id);
        $this->assertSame(['telekom', 'qualityhosting'], array_column($run->files, 'kind'));
        foreach ($run->files as $file) {
            Storage::disk(ReconciliationRun::DISK)->assertExists($file['path']);
        }

        Bus::assertDispatched(RunReconciliationJob::class, static fn(RunReconciliationJob $job): bool => $job->runId === $run->id);
    }

    public function test_store_requires_at_least_one_export(): void {
        Bus::fake();

        $this->actingAs($this->orgAdmin())
            ->from(route('finance.reselling.index'))
            ->post(route('finance.reselling.store'), ['reference_date' => '2025-01-01'])
            ->assertRedirect(route('finance.reselling.index'))
            ->assertSessionHasErrors('telekom');

        $this->assertSame(0, ReconciliationRun::query()->count());
        Bus::assertNotDispatched(RunReconciliationJob::class);
    }

    public function test_runner_produces_report_and_show_page_renders_it(): void {
        Bus::fake();
        $admin = $this->orgAdmin();
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key']);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster Bau GmbH',
            'company' => 'Muster Bau GmbH',
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-1',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
        ]);
        MarketplaceReconcilerTest::fakeLexoffice();

        $xlsx = QualityHostingContractsReaderTest::writeFixture();
        $priceList = sys_get_temp_dir() . '/qh-prices-' . uniqid() . '.xlsx';
        XlsxExport::saveToPath($priceList, ['Produkttarif', 'Vertragslaufzeit in Monaten', 'Zahlungsintervall', 'Gültig ab', 'Preis pro Monat', 'Hersteller-UVP pro Monat', 'Preis pro Zahlungsintervall', 'Hersteller-UVP pro Zahlungsintervall', 'Hersteller-Key', 'Offer-Key'], [
            ['Microsoft 365 Business Premium', 12, 'jährlich', null, 15.66, 19.06, 187.92, 228.72, 'CFQ7TTC0LCHC:0002', 'CFQ7TTC0LCHC-0002-P1Y-12M'],
            ['Exchange Online Plan 1', 12, 'jährlich', null, 2.86, 3.5, 34.32, 42.0, 'CFQ7TTC0LH16:0001', 'CFQ7TTC0LH16-0001-P1Y-12M'],
        ]);

        $this->actingAs($admin)->post(route('finance.reselling.store'), [
            'telekom' => UploadedFile::fake()->createWithContent('purchases.csv', (string) file_get_contents(MarketplaceReconcilerTest::FIXTURE)),
            'qualityhosting' => new UploadedFile($xlsx, 'Export.xlsx', null, null, true),
            'pricelist' => new UploadedFile($priceList, 'Preisliste.xlsx', null, null, true),
            'map' => UploadedFile::fake()->createWithContent('zuordnung.csv', "# Kommentar\nUnbekannt UG;11111111-2222-3333-4444-555555555555\n"),
            'reference_date' => '2026-09-03',
        ]);
        @unlink($xlsx);
        @unlink($priceList);

        /** @var ReconciliationRun $run */
        $run = ReconciliationRun::query()->firstOrFail();
        $source = (new LexofficeInvoiceLineReader('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();
        app(ReconciliationRunner::class)->run($run, $source);
        $run->refresh();

        $this->assertSame(ReconciliationRunStatus::Done, $run->status, (string) $run->error);
        $this->assertNotNull($run->finished_at);
        $summary = $run->summary ?? [];
        $this->assertSame(8, $summary['entitlements']);
        $this->assertSame(2, $summary['links'], 'Ablösungen aus beiden Fixtures');
        $this->assertSame(1, $summary['unmapped_companies'], 'nur Alt AG (nur im QH-Export, kein Kunde, kein Lexoffice-Treffer) bleibt offen');
        $unbekannt = collect($run->report['mappings'] ?? [])->firstWhere('company', 'Unbekannt UG');
        $this->assertNotNull($unbekannt);
        $this->assertTrue($unbekannt['resolved'], 'Zuordnungsdatei löst Unbekannt UG auf');
        $this->assertSame(\App\Services\Reselling\Marketplace\ContactMapping::SOURCE_MANUAL, $unbekannt['source']);
        $this->assertSame(['11111111-2222-3333-4444-555555555555'], $unbekannt['contact_ids']);
        $this->assertGreaterThan(0, $summary['counts']['missing']);
        $this->assertSame(1, $summary['counts']['underpriced']);

        $priceRows = $run->report['price_check'] ?? [];
        $this->assertNotEmpty($priceRows);
        $premium = collect($priceRows)->firstWhere('product', 'Microsoft 365 Business Premium');
        $this->assertNotNull($premium);
        $this->assertSame('187.92', $premium['list_price']['amount']);
        $this->assertSame('228.72', $premium['uvp']['amount']);
        $this->assertSame(9, $premium['running_quantity'], 'QH 8 + Telekom-Einzelposition');
        $this->assertSame('260.00', $premium['sales_median']['amount']);
        $this->assertSame(2, $premium['sales_samples']);
        $this->assertSame(38.4, $premium['margin_percent']);
        $this->assertNotContains('below_list', $premium['flags']);
        $this->assertSame(['contract_above_list'], array_values(array_intersect($premium['flags'], ['contract_above_list'])), 'Telekom-Altvertrag 244,76 liegt über der Liste 187,92');

        $this->actingAs($admin)->get(route('finance.reselling.show', $run->sqid))
            ->assertOk()
            ->assertSee(__('reselling.section.price'))
            ->assertSee('Muster Bau GmbH')
            ->assertSee(__('reselling.price_flag.contract_above_list'))
            ->assertSee(\App\Enums\Reselling\ReconciliationStatus::Missing->label());

        $this->actingAs($admin)->get(route('finance.reselling.show', ['run' => $run->sqid, 'status' => 'covered']))
            ->assertOk()
            ->assertSee('RE-2024-01');

        $download = $this->actingAs($admin)->get(route('finance.reselling.download', $run->sqid));
        $download->assertOk();
        $content = $download->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFFirma;Kunde;", $content);
        $this->assertStringContainsString('Muster Bau GmbH;Muster Bau GmbH;c-1;', $content);
        $this->assertStringContainsString(';Unter Einkauf;RE-2024-02;', $content);

        $this->actingAs($admin)->get(route('finance.reselling.index'))
            ->assertOk()
            ->assertSee(ReconciliationRunStatus::Done->label());

        $paths = array_column($run->files, 'path');
        $this->actingAs($admin)->delete(route('finance.reselling.destroy', $run->sqid))
            ->assertRedirect(route('finance.reselling.index'));
        $this->assertSame(0, ReconciliationRun::query()->count());
        foreach ($paths as $path) {
            Storage::disk(ReconciliationRun::DISK)->assertMissing($path);
        }
    }

    public function test_mappings_can_be_stored_in_the_ui_and_drive_the_next_run(): void {
        Bus::fake();
        $admin = $this->orgAdmin();
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key']);
        $partner = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'IT-Haus GmbH',
            'company' => 'IT-Haus GmbH',
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-partner',
            'referenceable_type' => $partner->getMorphClass(),
            'referenceable_id' => $partner->getKey(),
        ]);
        MarketplaceReconcilerTest::fakeLexoffice();

        $this->actingAs($admin)->post(route('finance.reselling.store'), [
            'telekom' => UploadedFile::fake()->createWithContent('purchases.csv', (string) file_get_contents(MarketplaceReconcilerTest::FIXTURE)),
            'reference_date' => '2025-01-01',
        ]);
        /** @var ReconciliationRun $run */
        $run = ReconciliationRun::query()->firstOrFail();
        $source = (new LexofficeInvoiceLineReader('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();
        app(ReconciliationRunner::class)->run($run, $source);
        $run->refresh();
        $this->assertSame(2, $run->summary['unmapped_companies'] ?? null, 'Beispiel Logistik (Namenssuche) findet Lexoffice, Unbekannt UG und Muster Bau nicht');

        // Dialog öffnen
        $this->actingAs($admin)->get(route('finance.reselling.mappings.create', ['run' => $run->sqid, 'company' => 'Unbekannt UG', 'key' => '100003']))
            ->assertOk()
            ->assertSee(__('reselling.mapping.title'))
            ->assertSee('Unbekannt UG')
            ->assertSee('IT-Haus GmbH');

        // Unbekannt UG über den Partner abrechnen → Fremdkunde wird angelegt
        $this->actingAs($admin)->post(route('finance.reselling.mappings.store', $run->sqid), [
            'company_name' => 'Unbekannt UG',
            'company_key' => '100003',
            'mode' => CompanyMappingMode::Partner->value,
            'customer' => $partner->sqid,
        ])->assertRedirect(route('finance.reselling.show', $run->sqid));

        $mapping = CompanyMapping::query()->where('normalized_name', 'unbekannt ug')->firstOrFail();
        $this->assertSame(CompanyMappingMode::Partner, $mapping->mode);
        $this->assertSame($partner->id, $mapping->customer_id);
        $this->assertSame('100003', $mapping->company_key);
        $this->assertSame('partner:' . $partner->sqid, $mapping->target());
        $this->assertSame(1, ForeignCustomer::query()->where('customer_id', $partner->id)->where('name', 'Unbekannt UG')->count(), 'Fremdkunde beim Partner angelegt');

        // Zweites Speichern legt keinen zweiten Fremdkunden an und aktualisiert die Zuordnung
        $this->actingAs($admin)->post(route('finance.reselling.mappings.store', $run->sqid), [
            'company_name' => 'Unbekannt UG',
            'mode' => CompanyMappingMode::Partner->value,
            'customer' => $partner->sqid,
        ]);
        $this->assertSame(1, CompanyMapping::query()->count());
        $this->assertSame(1, ForeignCustomer::query()->where('customer_id', $partner->id)->count());

        // Muster Bau direkt als Lexoffice-Kontakt
        $this->actingAs($admin)->post(route('finance.reselling.mappings.store', $run->sqid), [
            'company_name' => 'Muster Bau GmbH',
            'company_key' => '100001',
            'mode' => CompanyMappingMode::Contact->value,
            'contact_external_id' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
        ]);
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', CompanyMapping::query()->where('normalized_name', 'muster bau gmbh')->value('contact_external_id'));

        // Fehlender Kunde bei Modus „direkt"
        $this->actingAs($admin)->from(route('finance.reselling.show', $run->sqid))->post(route('finance.reselling.mappings.store', $run->sqid), [
            'company_name' => 'Beispiel Logistik',
            'mode' => CompanyMappingMode::Customer->value,
        ])->assertSessionHasErrors('customer');

        // Neu berechnen: Lauf zurück in die Warteschlange, Job erneut
        $this->actingAs($admin)->post(route('finance.reselling.rerun', $run->sqid))
            ->assertRedirect(route('finance.reselling.show', $run->sqid));
        $run->refresh();
        $this->assertSame(ReconciliationRunStatus::Queued, $run->status);
        $this->assertNull($run->report);
        // Ein Lauf aus der Zeit engerer Vorgaben bekommt beim Neuberechnen mindestens das aktuelle Fenster.
        $this->assertSame(\App\Services\Reselling\Marketplace\ReconciliationOptions::DEFAULT_BEFORE, $run->window_before);
        $this->assertSame(\App\Services\Reselling\Marketplace\ReconciliationOptions::DEFAULT_AFTER, $run->window_after);
        Bus::assertDispatched(RunReconciliationJob::class, static fn(RunReconciliationJob $job): bool => $job->runId === $run->id);

        // Zweiter Lauf nutzt die gespeicherten Zuordnungen
        app(ReconciliationRunner::class)->run($run, $source);
        $run->refresh();
        $this->assertSame(ReconciliationRunStatus::Done, $run->status, (string) $run->error);
        $mappings = collect($run->report['mappings']);
        $unbekannt = $mappings->firstWhere('company', 'Unbekannt UG');
        $this->assertSame(\App\Services\Reselling\Marketplace\ContactMapping::SOURCE_STORED, $unbekannt['source']);
        $this->assertSame('IT-Haus GmbH', $unbekannt['billed_via']);
        $this->assertSame(['c-partner'], $unbekannt['contact_ids']);
        $muster = $mappings->firstWhere('company', 'Muster Bau GmbH');
        $this->assertSame(['aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'], $muster['contact_ids']);
        $this->assertSame(0, $run->summary['unmapped_companies']);

        $this->actingAs($admin)->get(route('finance.reselling.show', $run->sqid))
            ->assertOk()
            ->assertSee(__('reselling.field.stored_mapping'))
            ->assertSee(CompanyMappingMode::Partner->label());

        // Zuordnung entfernen
        $this->actingAs($admin)->delete(route('finance.reselling.mappings.destroy', ['run' => $run->sqid, 'mapping' => $mapping->sqid]))
            ->assertRedirect(route('finance.reselling.show', $run->sqid));
        $this->assertSame(1, CompanyMapping::query()->count());
    }

    public function test_runner_marks_run_failed_when_lexoffice_rejects_the_key(): void {
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'expired']);
        \Tests\Support\FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => \Tests\Support\FakePluginHttp::response(['message' => 'Unauthorized'], 401),
        ]);
        Storage::disk(ReconciliationRun::DISK)->put('reselling/x/purchases.csv', (string) file_get_contents(MarketplaceReconcilerTest::FIXTURE));
        $run = ReconciliationRun::create([
            'organization_id' => $this->organization->id,
            'status' => ReconciliationRunStatus::Queued,
            'reference_date' => '2026-09-03',
            'files' => [['kind' => ReconciliationRun::KIND_TELEKOM, 'name' => 'purchases.csv', 'path' => 'reselling/x/purchases.csv']],
        ]);

        (new RunReconciliationJob($run->id))->handle(app(ReconciliationRunner::class));
        $run->refresh();

        $this->assertSame(ReconciliationRunStatus::Failed, $run->status);
        $this->assertStringContainsString('401', (string) $run->error);
        $this->assertNull($run->report);

        $this->actingAs($this->orgAdmin())->get(route('finance.reselling.show', $run->sqid))
            ->assertOk()
            ->assertSee(__('reselling.hint.run_failed'));
        $this->actingAs($this->orgAdmin())->get(route('finance.reselling.download', $run->sqid))
            ->assertRedirect(route('finance.reselling.show', $run->sqid));
    }
}
