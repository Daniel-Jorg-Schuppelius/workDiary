<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceNinjaArticleCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\InvoiceNinja;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Reselling\ResaleArticleRole;
use App\Models\Finance\{AccountingArticle, AccountingVoucherLine};
use App\Models\Reselling\ResaleArticleClassification;
use App\Plugins\InvoiceNinja\InvoiceNinjaPlugin;
use App\Plugins\InvoiceNinja\Services\InvoiceNinjaArticleCatalogSource;
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use App\Services\Finance\Accounting\Articles\ArticleMirror;
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, MirroredVoucherLine, VoucherMirror};
use App\Services\Platform\Catalog\{ArticleCatalog, CatalogArticle};
use App\Services\Reselling\Register\LicenseArticleClassifier;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Invoice-Ninja-Produkte (`GET /api/v1/products`) als Katalogquelle `inj`
 * (Feature 185, gap-09): Positionen tragen den `product_key`.
 */
class InvoiceNinjaArticleCatalogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const NINJA = 'https://ninja.example.test';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app()->instance('currentOrganization', $this->organization);
        $this->pluginSecret(InvoiceNinjaPlugin::ID, ['base_url' => self::NINJA, 'api_token' => 'ninja-token']);
        config()->set('plugins.invoiceninja.page_size', 2);
    }

    /** @return list<array<string, mixed>> */
    private function products(): array {
        return [
            ['id' => 'Opnel5aKBz', 'product_key' => 'Microsoft 365 Business Premium', 'notes' => 'Jahreslizenz', 'price' => 247.2, 'cost' => 200, 'tax_rate1' => 19, 'archived_at' => 0, 'is_deleted' => false, 'updated_at' => 1792000000],
            ['id' => 'Wpmbk5ezJn', 'product_key' => 'Altes Produkt', 'notes' => '', 'price' => 1, 'tax_rate1' => 19, 'archived_at' => 1700000000, 'is_deleted' => false, 'updated_at' => 1792000001],
            ['id' => 'Grp00001', 'product_key' => 'Support', 'notes' => 'Stundensatz', 'price' => 100, 'tax_rate1' => 19, 'archived_at' => 0, 'is_deleted' => true, 'updated_at' => 1792000002],
        ];
    }

    /** @param  callable(int): array<string, mixed>  $page */
    private function serve(callable $page): FakePluginHttp {
        return FakePluginHttp::fake([
            self::NINJA . '/api/v1/statics' => FakePluginHttp::response(['currencies' => [['id' => '3', 'code' => 'eur'], ['id' => '17', 'code' => 'chf']]]),
            self::NINJA . '/api/v1/companies/current' => FakePluginHttp::response(['data' => ['id' => 'VolejRejNm', 'settings' => ['currency_id' => '17']]]),
            self::NINJA . '/api/v1/products*' => static function (RequestInterface $request) use ($page): Response {
                parse_str($request->getUri()->getQuery(), $query);

                return FakePluginHttp::response($page((int) ($query['page'] ?? 1)));
            },
        ]);
    }

    private function fakePages(): FakePluginHttp {
        $products = $this->products();

        return $this->serve(static fn (int $page): array => [
            'data' => array_slice($products, ($page - 1) * 2, 2),
            'meta' => ['pagination' => ['total' => 3, 'per_page' => 2, 'current_page' => $page, 'total_pages' => 2]],
        ]);
    }

    private function source(): InvoiceNinjaArticleCatalogSource {
        $source = app(ArticleMirror::class)->sourceFor(InvoiceNinjaPlugin::ID);
        $this->assertInstanceOf(InvoiceNinjaArticleCatalogSource::class, $source);

        return $source;
    }

    /** @return array<string, CatalogArticle> */
    private function activeByName(): array {
        $byName = [];
        foreach (app(ArticleCatalog::class)->active((int) $this->organization->id) as $article) {
            if ($article->source === InvoiceNinjaArticleCatalogSource::PREFIX) {
                $byName[$article->name] = $article;
            }
        }

        return $byName;
    }

    public function test_reads_all_pages_in_company_currency_and_recognises_licence_articles(): void {
        $http = $this->fakePages();
        $orgId = (int) $this->organization->id;

        $this->assertTrue($this->source()->isConfigured($orgId), 'Token hinterlegt — der Katalog hängt nicht am Belegschalter');
        $result = app(ArticleMirror::class)->sync($orgId, $this->source());

        $http->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return str_contains($request->getUri()->getPath(), '/products') && ($query['status'] ?? null) === 'active,archived,deleted' && ($query['sort'] ?? null) === 'id|asc' && ($query['page'] ?? null) === '2';
        });
        $this->assertSame(['read' => 3, 'created' => 3, 'updated' => 0, 'archived' => 0, 'relinked' => 0], $result);
        $this->assertSame('CHF', AccountingArticle::query()->where('external_id', 'Opnel5aKBz')->sole()->currency->value, 'Firmenwährung des Tokens');

        $active = $this->activeByName();
        $this->assertSame(['Microsoft 365 Business Premium'], array_keys($active), 'archiviert und gelöscht sind inaktiv');
        $premium = $active['Microsoft 365 Business Premium'];
        $this->assertSame(['Microsoft 365 Business Premium', 'Jahreslizenz', null, 'Invoice Ninja', '247.2000'], [$premium->number, $premium->description, $premium->unitName, $premium->sourceLabel, $premium->netPrice?->getAmount()]);
        $this->assertTrue(app(LicenseArticleClassifier::class)->isLicense($orgId, $premium->key, $premium->name));
    }

    public function test_mirrored_lines_match_by_product_key_and_classify_as_licence(): void {
        $this->fakePages();
        $orgId = (int) $this->organization->id;
        app(ArticleMirror::class)->sync($orgId, $this->source());
        $key = $this->activeByName()['Microsoft 365 Business Premium']->key;

        $mirror = app(VoucherMirror::class);
        $counters = VoucherMirror::counters();
        $mirror->store($orgId, InvoiceNinjaPlugin::ID, new MirroredVoucher(externalId: 'invoice:Wpmbk5ezJn', direction: DocumentDirection::Outgoing, kind: DocumentKind::Invoice, number: 'R-0001', date: '2026-10-01', totalAmount: '294.17', currency: 'CHF'), $counters);
        // `line_items` tragen nur den `product_key`, keine Produkt-ID.
        $mirror->storeLines($orgId, InvoiceNinjaPlugin::ID, 'invoice:Wpmbk5ezJn', [
            new MirroredVoucherLine(position: 1, name: 'Microsoft 365 Business Premium', quantity: 1.0, unitNet: '247.2000', totalNet: '247.20', currency: 'CHF', externalArticleId: 'Microsoft 365 Business Premium'),
        ]);
        $line = AccountingVoucherLine::query()->sole();

        $this->assertSame($key, $line->article_ref);
        $this->assertTrue(app(LicenseArticleClassifier::class)->isLicense($orgId, $line->article_ref, $line->name));
        ResaleArticleClassification::query()->create(['organization_id' => $orgId, 'article_ref' => $key, 'role' => ResaleArticleRole::Excluded]);
        $this->assertFalse(app(LicenseArticleClassifier::class)->isLicense($orgId, $line->article_ref, $line->name));
    }

    public function test_page_limit_is_reported_instead_of_truncating(): void {
        $orgId = (int) $this->organization->id;
        AccountingArticle::query()->create(['organization_id' => $orgId, 'plugin_id' => InvoiceNinjaPlugin::ID, 'external_id' => 'Alt00001', 'number' => 'Alt', 'name' => 'Altbestand']);
        $products = $this->products();
        $http = $this->serve(static fn (int $page): array => [
            'data' => array_slice($products, 0, 2),
            'meta' => ['pagination' => ['total' => 9999, 'per_page' => 2, 'current_page' => $page, 'total_pages' => 999]],
        ]);

        try {
            app(ArticleMirror::class)->sync($orgId, $this->source());
            $this->fail('Seitenlimit muss gemeldet werden');
        } catch (IncompleteRemoteReadException $e) {
            $this->assertSame('page_limit', $e->reason);
        }

        // Zwei Abrufe für Währungen und Firma, dann 50 Seiten.
        $http->assertSentCount(52);
        $this->assertSame(1, AccountingArticle::query()->count());
        $this->assertNull(AccountingArticle::query()->where('external_id', 'Alt00001')->value('archived_at'));
    }
}
