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

use App\Enums\Reselling\ReconciliationRunStatus;
use App\Jobs\Reselling\RunReconciliationJob;
use App\Models\{Customer, ExternalReference};
use App\Models\Reselling\ReconciliationRun;
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
