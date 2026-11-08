<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AkauntingArticleCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\Akaunting;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Reselling\ResaleArticleRole;
use App\Models\Finance\{AccountingArticle, AccountingVoucherLine};
use App\Models\Reselling\ResaleArticleClassification;
use App\Plugins\Akaunting\AkauntingPlugin;
use App\Plugins\Akaunting\Services\AkauntingArticleCatalogSource;
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
 * Akaunting-Artikel (`GET /api/items`) als Katalogquelle `aka` (Feature 185, gap-09).
 */
class AkauntingArticleCatalogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const string AKAUNTING = 'https://akaunting.example.test';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app()->instance('currentOrganization', $this->organization);
        $this->pluginSecret(AkauntingPlugin::ID, ['base_url' => self::AKAUNTING, 'email' => 'api@example.test', 'password' => 'geheim', 'company_id' => 3]);
        config()->set('plugins.akaunting.page_size', 2);
    }

    /** @return list<array<string, mixed>> */
    private function items(): array {
        return [
            ['id' => 7, 'company_id' => 3, 'type' => 'service', 'name' => 'Microsoft 365 Business Premium', 'description' => 'Jahreslizenz', 'sale_price' => 247.2, 'purchase_price' => 200, 'category_id' => 1, 'enabled' => true, 'taxes' => ['data' => [['id' => 1, 'item_id' => 7, 'tax_id' => 2, 'tax' => ['id' => 2, 'name' => 'USt 19', 'rate' => 19, 'enabled' => true]]]]],
            ['id' => 8, 'company_id' => 3, 'type' => 'product', 'name' => 'Router', 'description' => null, 'sale_price' => 50, 'enabled' => false, 'taxes' => ['data' => []]],
            ['id' => 9, 'company_id' => 3, 'type' => 'service', 'name' => 'Support', 'description' => 'Stundensatz', 'sale_price' => 100, 'enabled' => true, 'taxes' => ['data' => []]],
        ];
    }

    /** @param  callable(int): array<string, mixed>  $page */
    private function serve(callable $page): FakePluginHttp {
        return FakePluginHttp::fake([
            self::AKAUNTING . '/api/items*' => static function (RequestInterface $request) use ($page): Response {
                parse_str($request->getUri()->getQuery(), $query);

                return FakePluginHttp::response($page((int) ($query['page'] ?? 1)));
            },
        ]);
    }

    private function fakePages(): FakePluginHttp {
        $items = $this->items();

        return $this->serve(static fn (int $page): array => [
            'data' => array_slice($items, ($page - 1) * 2, 2),
            'meta' => ['total' => 3, 'per_page' => 2, 'current_page' => $page, 'last_page' => 2],
        ]);
    }

    private function source(): AkauntingArticleCatalogSource {
        $source = app(ArticleMirror::class)->sourceFor(AkauntingPlugin::ID);
        $this->assertInstanceOf(AkauntingArticleCatalogSource::class, $source);

        return $source;
    }

    /** @return array<string, CatalogArticle> */
    private function activeByName(): array {
        $byName = [];
        foreach (app(ArticleCatalog::class)->active((int) $this->organization->id) as $article) {
            if ($article->source === AkauntingArticleCatalogSource::PREFIX) {
                $byName[$article->name] = $article;
            }
        }

        return $byName;
    }

    public function test_reads_all_pages_for_the_company_and_recognises_licence_articles(): void {
        $http = $this->fakePages();
        $orgId = (int) $this->organization->id;

        $this->assertTrue($this->source()->isConfigured($orgId), 'Zugang hinterlegt — der Katalog hängt nicht am Belegschalter');
        $result = app(ArticleMirror::class)->sync($orgId, $this->source());

        $http->assertSentCount(2);
        $http->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return $request->getHeaderLine('X-Company') === '3' && ($query['limit'] ?? null) === '2' && ($query['page'] ?? null) === '2';
        });
        $this->assertSame(['read' => 3, 'created' => 3, 'updated' => 0, 'archived' => 0, 'relinked' => 0], $result);

        $active = $this->activeByName();
        $this->assertSame(['Microsoft 365 Business Premium', 'Support'], array_keys($active), '`enabled` false ist inaktiv');
        $premium = $active['Microsoft 365 Business Premium'];
        $this->assertSame([null, 'Jahreslizenz', 'Akaunting', '247.2000', '19.00', 'service'], [$premium->number, $premium->description, $premium->sourceLabel, $premium->netPrice?->getAmount(), $premium->vatRate?->getNumericValue(), $premium->itemType]);
        $router = app(ArticleCatalog::class)->find($orgId, ArticleCatalog::key('aka', (int) AccountingArticle::query()->where('external_id', '8')->value('id')));
        $this->assertSame('material', $router?->itemType, 'Akaunting product ist Material');
        $this->assertTrue(app(LicenseArticleClassifier::class)->isLicense($orgId, $premium->key, $premium->name));
        $this->assertFalse(app(LicenseArticleClassifier::class)->isLicense($orgId, $active['Support']->key, 'Support'));
    }

    public function test_mirrored_lines_carry_the_catalog_key_and_classify_as_licence(): void {
        $this->fakePages();
        $orgId = (int) $this->organization->id;
        app(ArticleMirror::class)->sync($orgId, $this->source());
        $key = $this->activeByName()['Microsoft 365 Business Premium']->key;

        $mirror = app(VoucherMirror::class);
        $counters = VoucherMirror::counters();
        $mirror->store($orgId, AkauntingPlugin::ID, new MirroredVoucher(externalId: 'invoice:15', direction: DocumentDirection::Outgoing, kind: DocumentKind::Invoice, number: 'INV-00015', date: '2026-10-01', totalAmount: '294.17'), $counters);
        // Positionen tragen `item_id`.
        $mirror->storeLines($orgId, AkauntingPlugin::ID, 'invoice:15', [
            new MirroredVoucherLine(position: 1, name: 'Microsoft 365 Business Premium', quantity: 1.0, unitNet: '247.2000', totalNet: '247.20', externalArticleId: '7'),
        ]);
        $line = AccountingVoucherLine::query()->sole();

        $this->assertSame($key, $line->article_ref);
        $this->assertTrue(app(LicenseArticleClassifier::class)->isLicense($orgId, $line->article_ref, $line->name));
        ResaleArticleClassification::query()->create(['organization_id' => $orgId, 'article_ref' => $key, 'role' => ResaleArticleRole::Excluded]);
        $this->assertFalse(app(LicenseArticleClassifier::class)->isLicense($orgId, $line->article_ref, $line->name));
    }

    public function test_page_limit_is_reported_instead_of_truncating(): void {
        $orgId = (int) $this->organization->id;
        AccountingArticle::query()->create(['organization_id' => $orgId, 'plugin_id' => AkauntingPlugin::ID, 'external_id' => '99', 'name' => 'Altbestand']);
        $items = $this->items();
        $http = $this->serve(static fn (int $page): array => [
            'data' => array_slice($items, 0, 2),
            'meta' => ['total' => 9999, 'per_page' => 2, 'current_page' => $page, 'last_page' => 999],
        ]);

        try {
            app(ArticleMirror::class)->sync($orgId, $this->source());
            $this->fail('Seitenlimit muss gemeldet werden');
        } catch (IncompleteRemoteReadException $e) {
            $this->assertSame('page_limit', $e->reason);
        }

        $http->assertSentCount(50);
        $this->assertSame(1, AccountingArticle::query()->count());
        $this->assertNull(AccountingArticle::query()->where('external_id', '99')->value('archived_at'));
    }
}
