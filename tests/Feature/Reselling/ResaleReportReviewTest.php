<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleReportReviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Article\ArticleStatus;
use App\Enums\Reselling\{LinkOrigin, PeriodStatus, ResaleArticleRole};
use App\Enums\User\Permission;
use App\Models\{Article, Customer, LexofficeVoucher, LexofficeVoucherLine, Supplier, User};
use App\Models\Reselling\{ResalePeriodLink, ResalePurchaseEntry, ResaleSubscription};
use App\Services\Reselling\Marketplace\{ProviderInvoice, QualityHostingInvoiceReader};
use App\Services\Reselling\Register\{LicenseArticleClassifier, PeriodPlanner, ProviderInvoiceImport, PurchaseAllocator};
use App\Support\{Sqid, XlsxExport};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Review 2026-09-10 (Feature 152, MVP-765-Rest): Margenbericht mit
 * Zeitraum/Währungen und CSV/XLSX/PDF, Rechnungsvorschlag als XLSX,
 * Verlängerungen 30/60/90, „Abo ohne Rechnung > N Tage", Einkaufsseite mit
 * Filtern und PDF-Import samt Hinweisen, Rechte (view-only 200/403).
 */
class ResaleReportReviewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public const INVOICE_TEXT = <<<'TXT'
QualityHosting GmbH- Postbox 791407 - D-11516 Berlin
Rechnung
Rechnungsnr. 31970911 Kundennr. 95229
Rechnungsdatum 3. September 2026
Pos. Menge Beschreibung Einzelpreis Gesamtpreis
Endkunde: CNL00007 (Klimpel Bäder GmbH)
Vertrag: CNLCON00156
1 1 Microsoft 365 Business Premium 187,92 187,92
Grundgebühr pro Einheit
Dienst: CNLOUI
Vertrag: CNLCON00156
03.09.26 - 02.09.27
Total EUR ohne MwSt. 336,05
TXT;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'quantity' => 1, 'starts_on' => '2025-09-03', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'purchase_unit_price' => '187.92', 'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /** Nutzer nur mit „Reselling-Register sehen" — ohne pflegen/Rechnungsentwurf. */
    private function viewer(): User {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);
        $viewer = $this->orgUser();
        $viewer->givePermissionTo(Permission::ResellingView->value);
        $registrar->forgetCachedPermissions();

        return $viewer;
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /**
     * Gestreamtes XLSX in Zeilen (Sheet 1) auflösen.
     *
     * @return list<list<mixed>>
     */
    private function sheetRows(TestResponse $response): array {
        $response->assertOk()->assertHeader('content-type', XlsxExport::MIME);
        $path = tempnam(sys_get_temp_dir(), 'resale-xlsx');
        $this->assertNotFalse($path);
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        return $rows;
    }

    public function test_invoice_proposal_is_exported_as_xlsx_with_currency_column(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        $this->subscription(['customer_id' => $customer->id, 'quantity' => 2, 'starts_on' => '2025-09-20']); // 2026-09-20 noch nicht fällig

        $rows = $this->sheetRows($this->actingAs($admin)->get(route('finance.resale.report.export.xlsx')));
        $this->assertSame(__('resale.field.billed_to'), $rows[0][0]);
        $this->assertContains(__('resale.margin.currency'), $rows[0]);
        $this->assertCount(2, $rows, 'Kopf + eine offene Periode (2025)');
        $this->assertSame('Klimpel Bäder GmbH', $rows[1][0]);
        $this->assertSame('EUR', $rows[1][10]);
        $this->assertSame('494,40', (string) $rows[1][9], 'offener Betrag 2 × 247,20 als Dezimalstring');

        // CSV bleibt, gleiche Zeilen.
        $csv = $this->actingAs($admin)->get(route('finance.resale.report.export'));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Klimpel Bäder GmbH', $csv->streamedContent());
    }

    public function test_margin_report_filters_by_period_start_sorts_by_expected_sale_and_keeps_currencies_apart(): void {
        $admin = $this->orgAdmin();
        $big = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Gross GmbH']);
        $small = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klein GmbH']);
        $this->subscription(['customer_id' => $big->id, 'quantity' => 5, 'label' => 'Exchange Online Plan 1', 'starts_on' => '2026-03-01']);
        $this->subscription(['customer_id' => $small->id, 'quantity' => 1, 'starts_on' => '2024-01-10']);
        // Ohne Halter (kein Kunde, kein eigener Bestand): eigene Zeile „Noch nicht zugeordnet".
        $this->subscription(['label' => 'Ohne Halter', 'starts_on' => '2026-05-01', 'sale_unit_price' => '10.00']);
        // Eigener Bestand zählt nie.
        $this->subscription(['is_own_holding' => true, 'label' => 'Eigenbestand-Zeile', 'starts_on' => '2026-05-01']);
        // Schweizer Franken: nicht mit EUR summieren.
        $chf = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Helvetia AG']);
        $this->subscription(['customer_id' => $chf->id, 'currency' => 'CHF', 'label' => 'Backup CHF', 'starts_on' => '2026-02-01', 'sale_unit_price' => '300.00', 'purchase_unit_price' => '200.00']);

        $page = $this->actingAs($admin)->get(route('finance.resale.report.index'))->assertOk();
        $page->assertSee('Gross GmbH')->assertSee('Klein GmbH')->assertSee('Helvetia AG')->assertSee(__('resale.holder.unassigned'))->assertDontSee('Eigenbestand-Zeile');
        $page->assertSee('CHF');
        $page->assertSee(e(__('resale.margin.mixed_currencies', ['list' => 'CHF, EUR'])), false);
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'Klein GmbH'), strpos($html, 'Gross GmbH'), 'sortiert nach Soll-Verkauf absteigend');

        // Zeitraumfilter: nur Perioden mit Beginn ab 2026-02-15 → Klein GmbH (2024/2025/2026-01-10) fällt heraus.
        $this->actingAs($admin)->get(route('finance.resale.report.index', ['from' => '2026-02-15', 'to' => '2026-09-04']))
            ->assertOk()->assertSee('Gross GmbH')->assertDontSee('Klein GmbH')->assertDontSee('Helvetia AG');

        // Export: Währungsspalte, je Währung eigene Zeile, kein Summenmix.
        $rows = $this->sheetRows($this->actingAs($admin)->get(route('finance.resale.report.margin.export', ['format' => 'xlsx'])));
        $currencies = array_unique(array_map(static fn(array $r): string => (string) $r[2], array_slice($rows, 1)));
        sort($currencies);
        $this->assertSame(['CHF', 'EUR'], $currencies);
        $csv = $this->actingAs($admin)->get(route('finance.resale.report.margin.export', ['format' => 'csv']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Helvetia AG;CHF', $csv->streamedContent());
        $this->actingAs($admin)->get(route('finance.resale.report.margin.export', ['format' => 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('audit_logs', ['event' => 'report.exported']);
    }

    public function test_renewals_report_buckets_30_60_90_and_exports(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        $soon = $this->subscription(['customer_id' => $customer->id, 'label' => 'Bald', 'starts_on' => '2025-09-20']);          // verlängert 20.09.2026 (+16)
        $this->subscription(['customer_id' => $customer->id, 'label' => 'Mittel', 'starts_on' => '2025-11-01']);                  // verlängert 01.11.2026 (+58)
        $this->subscription(['customer_id' => $customer->id, 'label' => 'Gekündigt', 'starts_on' => '2025-12-01', 'renewal' => 'cancel', 'ends_on' => '2026-11-30']); // endet (+87)
        $this->subscription(['customer_id' => $customer->id, 'label' => 'Spät', 'starts_on' => '2026-01-15']);                    // verlängert 15.01.2027 (+133)
        $this->subscription(['customer_id' => $customer->id, 'label' => 'Vorbei', 'starts_on' => '2024-01-01', 'renewal' => 'cancel', 'ends_on' => '2026-08-01', 'status' => 'ended']);

        $page = $this->actingAs($admin)->get(route('finance.resale.report.renewals'))->assertOk();
        $page->assertSee('Bald')->assertSee('Mittel')->assertSee('Gekündigt')->assertDontSee('Spät')->assertDontSee('Vorbei');
        $page->assertSee(__('resale.renewals.mode_ends'))->assertSee(__('resale.renewals.mode_renews'))->assertSee('20.09.2026');
        $page->assertSee(route('finance.resale.show', $soon->sqid), false);

        $rows = $this->sheetRows($this->actingAs($admin)->get(route('finance.resale.report.renewals.export', ['format' => 'xlsx', 'from' => '2026-09-04', 'to' => '2026-10-04'])));
        $this->assertCount(2, $rows, 'Kopf + nur die Verlängerung in 30 Tagen');
        $this->assertSame('2026-09-20', $rows[1][0]);
        $this->assertSame('Bald', $rows[1][2]);

        $csv = $this->actingAs($admin)->get(route('finance.resale.report.renewals.export', ['format' => 'csv', 'from' => '2026-09-04', 'to' => '2027-03-04']));
        $csv->assertOk();
        $this->assertStringContainsString('Spät', $csv->streamedContent(), 'jenseits von 90 Tagen: Planungshorizont folgt dem Zeitraum');
        $this->assertStringContainsString('2026-11-30', $csv->streamedContent());
    }

    public function test_unbilled_report_lists_subscriptions_with_old_open_periods(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        $old = $this->subscription(['customer_id' => $customer->id, 'label' => 'Alt offen', 'starts_on' => '2025-09-03', 'quantity' => 2]); // 2025-09-03 offen (366 Tage)
        $this->subscription(['customer_id' => $customer->id, 'label' => 'Frisch offen', 'starts_on' => '2026-08-20']);                     // 15 Tage
        $this->subscription(['is_own_holding' => true, 'label' => 'Eigenbestand alt', 'starts_on' => '2025-01-01']);

        $page = $this->actingAs($admin)->get(route('finance.resale.report.unbilled'))->assertOk();
        $page->assertSee('Alt offen')->assertDontSee('Frisch offen')->assertDontSee('Eigenbestand alt');
        $page->assertSee(route('finance.resale.show', $old->sqid), false);
        $page->assertSee('988,80 €', false); // offener Betrag beider offener Perioden (2 × 2 × 247,20)

        $this->actingAs($admin)->get(route('finance.resale.report.unbilled', ['days' => 400]))->assertOk()->assertDontSee('Alt offen');
        $this->actingAs($admin)->get(route('finance.resale.report.unbilled', ['days' => 10]))->assertOk()->assertSee('Frisch offen');

        $rows = $this->sheetRows($this->actingAs($admin)->get(route('finance.resale.report.unbilled.export', ['format' => 'xlsx'])));
        $this->assertCount(2, $rows);
        $this->assertSame('Alt offen', $rows[1][0]);
        $this->assertSame('2025-09-03', $rows[1][4]);
        $this->assertSame(366, (int) $rows[1][5]);
    }

    public function test_purchase_page_filters_and_dialog_rejects_domain_provider(): void {
        $admin = $this->orgAdmin();
        $telekom = $this->subscription(['is_own_holding' => true, 'provider' => 'telekom_marketplace', 'external_id' => 'ent-1', 'label' => 'Telekom-Abo', 'starts_on' => '2026-01-01']);
        $qh = $this->subscription(['is_own_holding' => true, 'external_id' => 'CNLCON00156', 'label' => 'QH-Abo', 'starts_on' => '2025-09-03']);
        $period = static fn(ResaleSubscription $s) => $s->periods()->orderBy('starts_on')->firstOrFail();
        ResalePurchaseEntry::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $telekom->id, 'period_id' => $period($telekom)->id, 'provider' => 'telekom_marketplace',
            'source' => ResalePurchaseEntry::SOURCE_VOUCHER, 'document_number' => '726 039 1495', 'entry_date' => '2026-03-30', 'description' => 'Anteil März', 'net_amount' => '100.00', 'currency' => 'EUR', 'raw_hash' => 'h-t',
        ]);
        ResalePurchaseEntry::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $qh->id, 'period_id' => $period($qh)->id, 'provider' => 'qualityhosting',
            'source' => ResalePurchaseEntry::SOURCE_PROVIDER_INVOICE, 'document_number' => '31970911', 'entry_date' => '2026-09-03', 'description' => 'Microsoft 365 Business Premium', 'net_amount' => '187.92', 'currency' => 'EUR', 'raw_hash' => 'h-q',
        ]);

        $this->actingAs($admin)->get(route('finance.resale.purchases.index'))->assertOk()->assertSee('Telekom-Abo')->assertSee('QH-Abo');
        $this->actingAs($admin)->get(route('finance.resale.purchases.index', ['provider' => 'qualityhosting']))->assertOk()->assertSee('QH-Abo')->assertDontSee('Telekom-Abo');
        $this->actingAs($admin)->get(route('finance.resale.purchases.index', ['source' => ResalePurchaseEntry::SOURCE_VOUCHER]))->assertOk()->assertSee('Telekom-Abo')->assertDontSee('QH-Abo');
        $this->actingAs($admin)->get(route('finance.resale.purchases.index', ['q' => '31970911']))->assertOk()->assertSee('QH-Abo')->assertDontSee('Telekom-Abo');
        $this->actingAs($admin)->get(route('finance.resale.purchases.index', ['q' => '%']))->assertOk()->assertDontSee('QH-Abo');
        $this->actingAs($admin)->get(route('finance.resale.purchases.index', ['from' => '2026-03-01', 'to' => '2026-03-31']))->assertOk()->assertSee('Telekom-Abo')->assertDontSee('QH-Abo');

        // Dialog: Suchfeld vorhanden, Domain-Reselling nicht wählbar, Vorfilter per q.
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Telekom Deutschland GmbH']);
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'pv-1', 'supplier_id' => $supplier->id, 'voucher_type' => 'purchaseinvoice', 'voucher_status' => 'paid',
            'voucher_number' => '726 039 1495', 'voucher_date' => '2026-03-30', 'total_amount' => 2043.55, 'currency' => 'EUR', 'archived' => false,
        ]);
        $dialog = $this->actingAs($admin)->get(route('finance.resale.purchases.create', ['q' => 'Telekom']))->assertOk();
        $dialog->assertSee('data-filter-search', false)->assertSee('726 039 1495')->assertDontSee('value="domainreselling"', false);
        $this->actingAs($admin)->get(route('finance.resale.purchases.create', ['q' => 'Nirgends']))->assertOk()->assertDontSee('726 039 1495');

        $this->actingAs($admin)->postJson(route('finance.resale.purchases.store'), [
            'document' => Sqid::encode(LexofficeVoucher::class, $voucher->id), 'provider' => 'domainreselling', 'net_amount' => '10.00', 'month' => '2026-03',
        ])->assertStatus(422)->assertJsonPath('errors.provider.0', __('resale.purchase_dialog.domain_provider'));
        $this->actingAs($admin)->postJson(route('finance.resale.purchases.store'), [
            'document' => 'nope', 'provider' => 'telekom_marketplace', 'net_amount' => '10.00', 'month' => '2026-03',
        ])->assertStatus(422)->assertJsonValidationErrors(['document']);
    }

    public function test_pdf_import_reports_line_issues_and_total_mismatch_without_aborting(): void {
        $admin = $this->orgAdmin();
        $this->subscription(['is_own_holding' => true, 'external_id' => 'CNLCON00156', 'company_name' => 'Klimpel Bäder GmbH', 'starts_on' => '2025-09-03']);

        $this->actingAs($admin)->get(route('finance.resale.purchases.import.create'))->assertOk()->assertSee('purchase-files');

        // Reader ist final: die Naht `read()` des Import-Services antwortet mit dem Text-Fixture statt PDF-Textextraktion.
        app()->instance(ProviderInvoiceImport::class, new class(app(PurchaseAllocator::class), new QualityHostingInvoiceReader) extends ProviderInvoiceImport {
            protected function read(string $path): ProviderInvoice {
                return $this->reader->parse(ResaleReportReviewTest::INVOICE_TEXT);
            }
        });
        $upload = UploadedFile::fake()->createWithContent('31970911.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");

        $response = $this->actingAs($admin)->post(route('finance.resale.purchases.import.store'), ['files' => [$upload]]);
        $response->assertRedirect(route('finance.resale.purchases.index'))->assertSessionHas('success')->assertSessionHas('warning');
        $issues = session('resale_purchase_issues');
        $this->assertIsArray($issues);
        $this->assertCount(1, $issues, 'Summenabweichung 187,92 ≠ 336,05 als Hinweis');
        $this->assertStringStartsWith('31970911.pdf: ', $issues[0]);
        $this->assertSame(1, ResalePurchaseEntry::query()->count(), 'Import läuft trotz Hinweis durch');
        $this->assertStringContainsString('187,92 €', (string) session('success'));

        // Hinweise stehen auf der Einkaufsseite als Liste.
        $this->actingAs($admin)->withSession(['resale_purchase_issues' => $issues])->get(route('finance.resale.purchases.index'))
            ->assertOk()->assertSee(e($issues[0]), false);

        // Kein PDF → 422 aus dem FormRequest.
        $this->actingAs($admin)->postJson(route('finance.resale.purchases.import.store'), ['files' => [UploadedFile::fake()->create('liste.csv', 1, 'text/csv')]])
            ->assertStatus(422)->assertJsonValidationErrors(['files.0']);
    }

    public function test_view_only_user_reads_reports_but_cannot_change_anything(): void {
        $viewer = $this->viewer();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        $subscription = $this->subscription(['customer_id' => $customer->id, 'quantity' => 2]);
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'inv-1', 'contact_external_id' => 'c-kl', 'voucher_type' => 'invoice', 'voucher_status' => 'paid',
            'voucher_number' => 'RE/2025/0820', 'voucher_date' => '2025-10-14', 'total_amount' => 494.4, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        $line = LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 24, 'unit_name' => 'Monat',
            'unit_net' => '20.60', 'total_net' => '494.40', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
        ResalePeriodLink::create([
            'organization_id' => $this->organization->id, 'period_id' => $period->id, 'subscription_id' => $subscription->id, 'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id,
            'voucher_number' => 'RE/2025/0820', 'voucher_date' => '2025-10-14', 'quantity' => 2, 'months' => 24, 'amount' => '494.40', 'currency' => 'EUR', 'origin' => LinkOrigin::Confirmed, 'confirmed_at' => now(),
        ]);
        $period->forceFill(['status' => PeriodStatus::Billed, 'decided_at' => now()])->save();
        $entry = ResalePurchaseEntry::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $subscription->id, 'period_id' => $period->id, 'provider' => 'qualityhosting',
            'source' => ResalePurchaseEntry::SOURCE_PROVIDER_INVOICE, 'document_number' => '31970911', 'entry_date' => '2025-09-03', 'net_amount' => '187.92', 'currency' => 'EUR', 'raw_hash' => 'h-1',
        ]);

        foreach (['finance.resale.report.index', 'finance.resale.report.renewals', 'finance.resale.report.unbilled', 'finance.resale.prices', 'finance.resale.products', 'finance.resale.purchases.index'] as $route) {
            $this->actingAs($viewer)->get(route($route))->assertOk();
        }
        $this->actingAs($viewer)->get(route('finance.resale.report.export.xlsx'))->assertOk();
        $this->actingAs($viewer)->get(route('finance.resale.report.margin.export', ['format' => 'csv']))->assertOk();
        $this->actingAs($viewer)->get(route('finance.resale.purchases.index'))->assertDontSee(route('finance.resale.purchases.destroy', $entry->sqid), false);

        $this->actingAs($viewer)->post(route('finance.resale.products.store'), ['article_id' => 'x', 'role' => 'license'])->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.resale.purchases.store'), ['document' => 'x', 'provider' => 'qualityhosting', 'net_amount' => '1', 'month' => '2026-03'])->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.resale.purchases.import.store'), [])->assertForbidden();
        $this->actingAs($viewer)->delete(route('finance.resale.purchases.destroy', $entry->sqid))->assertForbidden();
        $this->actingAs($viewer)->get(route('finance.resale.periods.draft.create'))->assertForbidden();
        $this->assertSame(1, ResalePurchaseEntry::query()->count());
    }
    /**
     * Review 2026-09-11: die Produktseite führt auch die aktiven lokalen
     * Artikel und speichert deren Einstufung (`article_type=local`, Sqid des
     * Artikels); ohne `article_type` bleibt Lexoffice der Default.
     */
    public function test_products_page_lists_active_local_articles_and_saves_their_role(): void {
        $admin = $this->orgAdmin();
        $cloud = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Cloud-Arbeitsplatz Premium', 'number' => 'CAP', 'default_sale_price' => '20.60', 'currency' => 'EUR']);
        Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Altes Produkt', 'status' => ArticleStatus::Retired->value]);
        $classifier = new LicenseArticleClassifier;
        $this->assertFalse($classifier->isLicense($cloud), 'ohne Einstufung: Name verrät kein Produkt');

        $this->actingAs($admin)->get(route('finance.resale.products'))
            ->assertOk()
            ->assertSee(__('resale.products_local.title'))
            ->assertSee('Cloud-Arbeitsplatz Premium')
            ->assertSee('name="article_type" value="local"', false)
            ->assertDontSee('Altes Produkt');

        $this->actingAs($admin)->post(route('finance.resale.products.store'), ['article_type' => 'local', 'article_id' => $cloud->sqid, 'role' => 'license'])
            ->assertRedirect(route('finance.resale.products'))
            ->assertSessionHas('success');
        $cloud->refresh();
        $this->assertSame(ResaleArticleRole::License, $cloud->resale_role);
        $this->assertTrue($classifier->isLicense($cloud), 'Betreiber-Einstufung gewinnt');

        $this->actingAs($admin)->post(route('finance.resale.products.store'), ['article_type' => 'local', 'article_id' => $cloud->sqid, 'role' => 'auto'])
            ->assertRedirect(route('finance.resale.products'));
        $this->assertNull($cloud->fresh()?->resale_role, '„auto" löscht die Übersteuerung');

        // Unbekannte Art: Validierungsfehler, nichts gespeichert.
        $this->actingAs($admin)->from(route('finance.resale.products'))->post(route('finance.resale.products.store'), ['article_type' => 'other', 'article_id' => $cloud->sqid, 'role' => 'license'])
            ->assertSessionHasErrors('article_type');
        $this->assertNull($cloud->fresh()?->resale_role);
        // Nur sehen: 403 auch für lokale Artikel.
        $this->actingAs($this->viewer())->post(route('finance.resale.products.store'), ['article_type' => 'local', 'article_id' => $cloud->sqid, 'role' => 'license'])->assertForbidden();
    }
}
