<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskArticleCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\SevDesk;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Reselling\ResaleArticleRole;
use App\Models\Finance\{AccountingArticle, AccountingVoucherLine};
use App\Models\Platform\PluginSetting;
use App\Models\Reselling\ResaleArticleClassification;
use App\Plugins\SevDesk\Services\SevDeskArticleCatalogSource;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use App\Services\Finance\Accounting\Articles\ArticleMirror;
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, MirroredVoucherLine, VoucherMirror};
use App\Services\Platform\Catalog\{ArticleCatalog, CatalogArticle};
use App\Services\Reselling\Register\LicenseArticleClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * sevDesk-Artikel (`GET /Part`) als Katalogquelle `sd` (Feature 185, gap-09).
 */
class SevDeskArticleCatalogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string PARTS = 'https://my.sevdesk.de/api/v1/Part*';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => SevDeskPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'tok-123'],
        ]);
        config()->set('plugins.sevdesk.page_size', 2);
    }

    /** @return list<array<string, mixed>> */
    private function parts(): array {
        return [
            ['id' => '501', 'objectName' => 'Part', 'name' => 'Microsoft 365 Business Standard', 'partNumber' => 'M365-BS', 'text' => 'Jahreslizenz', 'priceNet' => 10.5, 'price' => 10.5, 'taxRate' => 19, 'status' => '100', 'stockEnabled' => false, 'unity' => ['id' => '1', 'objectName' => 'Unity', 'name' => 'Stück']],
            ['id' => '502', 'objectName' => 'Part', 'name' => 'Altes Produkt', 'partNumber' => 'ALT-1', 'priceNet' => 1, 'taxRate' => 19, 'status' => '50', 'unity' => ['id' => '1', 'objectName' => 'Unity']],
            ['id' => '503', 'objectName' => 'Part', 'name' => 'Server-Rack', 'partNumber' => 'SR-42', 'priceNet' => 899.99, 'taxRate' => 19, 'status' => '100', 'stockEnabled' => true, 'unity' => ['id' => '1', 'objectName' => 'Unity', 'name' => 'Stück']],
        ];
    }

    private function fakePages(): FakePluginHttp {
        $parts = $this->parts();

        return FakePluginHttp::fake([
            self::PARTS => [
                FakePluginHttp::response(['objects' => array_slice($parts, 0, 2), 'total' => '3']),
                FakePluginHttp::response(['objects' => array_slice($parts, 2, 1), 'total' => '3']),
            ],
        ]);
    }

    private function source(): SevDeskArticleCatalogSource {
        $source = app(ArticleMirror::class)->sourceFor(SevDeskPlugin::ID);
        $this->assertInstanceOf(SevDeskArticleCatalogSource::class, $source);

        return $source;
    }

    /** @return array<string, CatalogArticle> */
    private function activeByName(): array {
        $byName = [];
        foreach (app(ArticleCatalog::class)->active((int) $this->organization->id) as $article) {
            if ($article->source === SevDeskArticleCatalogSource::PREFIX) {
                $byName[$article->name] = $article;
            }
        }

        return $byName;
    }

    public function test_reads_all_pages_with_embedded_unit_and_recognises_licence_articles(): void {
        $http = $this->fakePages();
        $orgId = (int) $this->organization->id;

        $result = app(ArticleMirror::class)->sync($orgId, $this->source());

        $http->assertSentCount(2);
        $http->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['countAll'] ?? null) === 'true' && ($query['embed'] ?? null) === 'unity' && ($query['offset'] ?? null) === '2';
        });
        $this->assertSame(['read' => 3, 'created' => 3, 'updated' => 0, 'archived' => 0, 'relinked' => 0], $result);

        $active = $this->activeByName();
        $this->assertSame(['Microsoft 365 Business Standard', 'Server-Rack'], array_keys($active), 'Status 50 ist inaktiv');
        $standard = $active['Microsoft 365 Business Standard'];
        $this->assertSame(['M365-BS', 'Stück', 'Jahreslizenz', 'sevDesk', '10.5000', 'service'], [$standard->number, $standard->unitName, $standard->description, $standard->sourceLabel, $standard->netPrice?->getAmount(), $standard->itemType]);
        $this->assertSame('material', $active['Server-Rack']->itemType, 'Lagerführung heißt Material');
        $this->assertTrue(app(LicenseArticleClassifier::class)->isLicense($orgId, $standard->key, $standard->name));
        $this->assertFalse(app(LicenseArticleClassifier::class)->isLicense($orgId, $active['Server-Rack']->key, 'Server-Rack'));
    }

    public function test_mirrored_lines_carry_the_catalog_key_and_classify_as_licence(): void {
        $this->fakePages();
        $orgId = (int) $this->organization->id;
        app(ArticleMirror::class)->sync($orgId, $this->source());
        $key = $this->activeByName()['Microsoft 365 Business Standard']->key;

        $mirror = app(VoucherMirror::class);
        $counters = VoucherMirror::counters();
        $mirror->store($orgId, SevDeskPlugin::ID, new MirroredVoucher(externalId: 'invoice:77', direction: DocumentDirection::Outgoing, kind: DocumentKind::Invoice, number: 'RE-2026-9002', date: '2026-10-01', totalAmount: '12.50'), $counters);
        // Positionen tragen `part.id` (Einzelabruf `getPositions`, embed=part).
        $mirror->storeLines($orgId, SevDeskPlugin::ID, 'invoice:77', [
            new MirroredVoucherLine(position: 1, name: 'Microsoft 365 Business Standard', quantity: 1.0, unitNet: '10.5000', totalNet: '10.50', externalArticleId: '501'),
        ]);
        $line = AccountingVoucherLine::query()->sole();

        $this->assertSame($key, $line->article_ref);
        $this->assertTrue(app(LicenseArticleClassifier::class)->isLicense($orgId, $line->article_ref, $line->name));
        ResaleArticleClassification::query()->create(['organization_id' => $orgId, 'article_ref' => $key, 'role' => ResaleArticleRole::Excluded]);
        $this->assertFalse(app(LicenseArticleClassifier::class)->isLicense($orgId, $line->article_ref, $line->name));
    }

    public function test_page_limit_is_reported_instead_of_truncating(): void {
        $orgId = (int) $this->organization->id;
        AccountingArticle::query()->create(['organization_id' => $orgId, 'plugin_id' => SevDeskPlugin::ID, 'external_id' => '999', 'name' => 'Altbestand']);
        $http = FakePluginHttp::fake([
            self::PARTS => fn () => FakePluginHttp::response(['objects' => array_slice($this->parts(), 0, 2), 'total' => '99999']),
        ]);

        try {
            app(ArticleMirror::class)->sync($orgId, $this->source());
            $this->fail('Seitenlimit muss gemeldet werden');
        } catch (IncompleteRemoteReadException $e) {
            $this->assertSame('page_limit', $e->reason);
        }

        $http->assertSentCount(20);
        $this->assertSame(1, AccountingArticle::query()->count());
        $this->assertNull(AccountingArticle::query()->where('external_id', '999')->value('archived_at'));
    }
}
