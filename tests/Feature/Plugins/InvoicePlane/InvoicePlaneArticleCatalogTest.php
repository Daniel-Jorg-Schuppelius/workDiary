<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePlaneArticleCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\InvoicePlane;

use App\Enums\Reselling\ResaleArticleRole;
use App\Models\Finance\{AccountingArticle, AccountingVoucherLine};
use App\Models\Reselling\ResaleArticleClassification;
use App\Plugins\InvoicePlane\Schema\{ProductReader, VoucherReader, VoucherReaderFactory};
use App\Plugins\InvoicePlane\Services\{InvoicePlaneArticleCatalogSource, InvoicePlaneVoucherPullService};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use App\Services\Finance\Accounting\Articles\ArticleMirror;
use App\Services\Platform\Catalog\{ArticleCatalog, CatalogArticle};
use App\Services\Reselling\Register\LicenseArticleClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * InvoicePlane-Artikel (`ip_products`) als Katalogquelle `ipl` (Feature 185,
 * gap-09) — über die Bridge, nicht über HTTP: der Leser ist ein Fake.
 */
final class InvoicePlaneArticleCatalogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** @return list<array<string, mixed>> */
    private function products(): array {
        return [
            ['product_id' => 1, 'family_id' => 3, 'family_name' => 'Lizenzen', 'product_sku' => 'MS-BP', 'product_name' => 'Microsoft 365 Business Premium', 'product_description' => 'Jahreslizenz', 'product_price' => 247.2, 'tax_rate_percent' => 19.0, 'unit_name' => 'Stück'],
            ['product_id' => 2, 'family_id' => null, 'family_name' => null, 'product_sku' => 'BER', 'product_name' => 'Beratung', 'product_description' => '', 'product_price' => 120, 'tax_rate_percent' => 19.0, 'unit_name' => 'Std.'],
            ['product_id' => 3, 'family_id' => 3, 'family_name' => 'Lizenzen', 'product_sku' => 'DOM-DE', 'product_name' => 'Domain .de', 'product_description' => '', 'product_price' => 12, 'tax_rate_percent' => 19.0, 'unit_name' => null],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @param  list<array<string, mixed>>  $invoices
     */
    private function bindReader(array $products, array $invoices = []): void {
        $reader = new class($products, $invoices) implements ProductReader, VoucherReader {
            /** @var list<int> Offsets der Artikelabrufe */
            public array $offsets = [];

            /**
             * @param  list<array<string, mixed>>  $products
             * @param  list<array<string, mixed>>  $invoices
             */
            public function __construct(private readonly array $products, private readonly array $invoices) {}

            public function invoicesSince(?string $sinceDate, int $offset, int $limit): array {
                return array_slice($this->invoices, $offset, $limit);
            }

            public function invoiceItems(array $invoiceIds): array {
                return $invoiceIds === [] ? [] : [
                    ['invoice_id' => $invoiceIds[0], 'item_id' => 1, 'item_order' => 1, 'item_product_id' => 1, 'item_product_unit' => 'Stück', 'item_name' => 'Microsoft 365 Business Premium', 'item_description' => 'Jahreslizenz', 'item_quantity' => 2, 'item_price' => 247.2],
                ];
            }

            public function products(int $offset, int $limit): array {
                $this->offsets[] = $offset;

                return array_slice($this->products, $offset, $limit);
            }
        };
        $this->app->instance(VoucherReaderFactory::class, new class($reader) implements VoucherReaderFactory {
            public function __construct(private readonly VoucherReader $reader) {}

            public function for(int $organizationId): VoucherReader {
                return $this->reader;
            }
        });
    }

    private function source(): InvoicePlaneArticleCatalogSource {
        $source = app(ArticleMirror::class)->sourceFor(InvoicePlaneVoucherPullService::PLUGIN_ID);
        $this->assertInstanceOf(InvoicePlaneArticleCatalogSource::class, $source);

        return $source;
    }

    /** @return array<string, CatalogArticle> */
    private function activeByName(): array {
        $byName = [];
        foreach (app(ArticleCatalog::class)->active((int) $this->organization->id) as $article) {
            if ($article->source === InvoicePlaneArticleCatalogSource::PREFIX) {
                $byName[$article->name] = $article;
            }
        }

        return $byName;
    }

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('invoiceplane.query.page_size', 2);
    }

    public function test_without_product_reader_the_source_is_not_configured(): void {
        $this->assertFalse($this->source()->isConfigured((int) $this->organization->id), 'Null-Leser: kein Artikelzugriff, kein erfundener Artikel');
        $this->assertSame([], $this->source()->fetch((int) $this->organization->id));
    }

    public function test_reads_all_pages_and_recognises_licence_articles(): void {
        $this->bindReader($this->products());
        $orgId = (int) $this->organization->id;

        $this->assertTrue($this->source()->isConfigured($orgId));
        $result = app(ArticleMirror::class)->sync($orgId, $this->source());

        $this->assertSame(['read' => 3, 'created' => 3, 'updated' => 0, 'archived' => 0, 'relinked' => 0], $result);
        $active = $this->activeByName();
        $this->assertSame(['Beratung', 'Domain .de', 'Microsoft 365 Business Premium'], array_keys($active));
        $premium = $active['Microsoft 365 Business Premium'];
        $this->assertSame(['MS-BP', 'Stück', 'Lizenzen · Jahreslizenz', 'InvoicePlane', '247.2000'], [$premium->number, $premium->unitName, $premium->description, $premium->sourceLabel, $premium->netPrice?->getAmount()]);
        $this->assertSame('Lizenzen', $active['Domain .de']->description, 'Familie ohne Beschreibung');
        $classifier = app(LicenseArticleClassifier::class);
        $this->assertTrue($classifier->isLicense($orgId, $premium->key, $premium->name));
        $this->assertTrue($classifier->isLicense($orgId, $active['Domain .de']->key, 'Domain .de'), 'Domain-Produkt am Namen erkannt');
        $this->assertFalse($classifier->isLicense($orgId, $active['Beratung']->key, 'Beratung'));
    }

    public function test_pulled_invoice_lines_carry_the_catalog_key_via_item_product_id(): void {
        /** @var array{rows: list<array<string, mixed>>} $fixture */
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/Plugins/InvoicePlane/invoices.json')), true);
        $this->bindReader($this->products(), array_slice($fixture['rows'], 0, 1));
        $orgId = (int) $this->organization->id;
        app(ArticleMirror::class)->sync($orgId, $this->source());
        $key = $this->activeByName()['Microsoft 365 Business Premium']->key;

        app(InvoicePlaneVoucherPullService::class)->pull($orgId);

        $line = AccountingVoucherLine::query()->sole();
        $this->assertSame(['1', $key, 'Stück'], [$line->external_article_id, $line->article_ref, $line->unit_name]);
        $this->assertTrue(app(LicenseArticleClassifier::class)->isLicense($orgId, $line->article_ref, $line->name));
        ResaleArticleClassification::query()->create(['organization_id' => $orgId, 'article_ref' => $key, 'role' => ResaleArticleRole::Excluded]);
        $this->assertFalse(app(LicenseArticleClassifier::class)->isLicense($orgId, $line->article_ref, $line->name));
    }

    public function test_row_limit_is_reported_instead_of_truncating(): void {
        $orgId = (int) $this->organization->id;
        AccountingArticle::query()->create(['organization_id' => $orgId, 'plugin_id' => InvoicePlaneVoucherPullService::PLUGIN_ID, 'external_id' => '9', 'name' => 'Altbestand']);
        // Mehr Zeilen als `max_rows` liest die Bridge nicht: eine volle letzte Seite heißt unvollständig.
        config()->set('invoiceplane.query.max_rows', 2);
        $this->bindReader($this->products());

        try {
            app(ArticleMirror::class)->sync($orgId, $this->source());
            $this->fail('Zeilenlimit muss gemeldet werden');
        } catch (IncompleteRemoteReadException $e) {
            $this->assertSame('page_limit', $e->reason);
        }

        $this->assertSame(1, AccountingArticle::query()->count());
        $this->assertNull(AccountingArticle::query()->where('external_id', '9')->value('archived_at'));
    }
}
