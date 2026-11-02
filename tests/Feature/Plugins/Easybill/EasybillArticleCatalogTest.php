<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillArticleCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\Easybill;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Reselling\ResaleArticleRole;
use App\Models\Finance\{AccountingArticle, AccountingVoucherLine};
use App\Models\Platform\PluginSetting;
use App\Models\Reselling\ResaleArticleClassification;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillArticleCatalogSource;
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use App\Services\Finance\Accounting\Articles\ArticleMirror;
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, MirroredVoucherLine, VoucherMirror};
use App\Services\Platform\Catalog\{ArticleCatalog, CatalogArticle};
use App\Services\Reselling\Register\LicenseArticleClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * easybill-Artikel als Katalogquelle `eb` (Feature 185, gap-09): vollständig
 * gelesen, Lizenzartikel erkannt, Spiegelpositionen tragen den Katalogschlüssel.
 */
class EasybillArticleCatalogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const POSITIONS = 'https://api.easybill.de/rest/v1/positions*';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EasybillPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'eb-key'],
        ]);
        // Zwei Artikel je Seite, damit die Fixtures zwei echte Seiten sind.
        config()->set('plugins.easybill.page_size', 2);
    }

    /** @return list<array<string, mixed>> */
    private function positions(): array {
        return [
            ['id' => 11, 'type' => 'SERVICE', 'number' => 'MS-BP', 'description' => "Microsoft 365 Business Premium\nJahreslizenz", 'unit' => 'Stück', 'vat_percent' => 19, 'sale_price' => 24720, 'price_type' => 'NETTO', 'archived' => false],
            ['id' => 12, 'type' => 'SERVICE', 'number' => 'SUP-H', 'description' => 'Support-Stunde', 'unit' => 'Std.', 'vat_percent' => 19, 'sale_price' => 11900, 'price_type' => 'BRUTTO', 'archived' => false],
            ['id' => 13, 'type' => 'TEXT', 'number' => '', 'description' => 'Hinweistext'],
            ['id' => 14, 'type' => 'PRODUCT', 'number' => 'HW-RT', 'description' => 'Router', 'unit' => 'Stück', 'vat_percent' => 19, 'sale_price' => 5000, 'price_type' => 'NETTO', 'archived' => true],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private static function page(int $page, int $pages, int $total, array $items): array {
        return ['page' => $page, 'pages' => $pages, 'limit' => 2, 'total' => $total, 'items' => $items];
    }

    private function fakePages(): FakePluginHttp {
        $positions = $this->positions();

        return FakePluginHttp::fake([
            self::POSITIONS => [
                FakePluginHttp::response(self::page(1, 2, 4, array_slice($positions, 0, 2))),
                FakePluginHttp::response(self::page(2, 2, 4, array_slice($positions, 2, 2))),
            ],
        ]);
    }

    private function source(): EasybillArticleCatalogSource {
        $source = app(ArticleMirror::class)->sourceFor(EasybillPlugin::ID);
        $this->assertInstanceOf(EasybillArticleCatalogSource::class, $source, 'Quelle beim Booten registriert');

        return $source;
    }

    /** @return array<string, CatalogArticle> Name → Katalogeintrag der Quelle */
    private function activeByName(): array {
        $byName = [];
        foreach (app(ArticleCatalog::class)->active((int) $this->organization->id) as $article) {
            if ($article->source === EasybillArticleCatalogSource::PREFIX) {
                $byName[$article->name] = $article;
            }
        }

        return $byName;
    }

    private function storeVoucherWithLine(string $externalArticleId): AccountingVoucherLine {
        $mirror = app(VoucherMirror::class);
        $counters = VoucherMirror::counters();
        $mirror->store((int) $this->organization->id, EasybillPlugin::ID, new MirroredVoucher(externalId: '4711', direction: DocumentDirection::Outgoing, kind: DocumentKind::Invoice, number: 'RE-2026-9001', date: '2026-10-01', totalAmount: '588.34', netAmount: '494.40'), $counters);
        $mirror->storeLines((int) $this->organization->id, EasybillPlugin::ID, '4711', [
            new MirroredVoucherLine(position: 1, name: 'Microsoft 365 Business Premium', quantity: 2.0, unitNet: '247.2000', totalNet: '494.40', externalArticleId: $externalArticleId, unitName: 'Stück', taxRate: '19'),
        ]);

        return AccountingVoucherLine::query()->sole();
    }

    public function test_reads_all_pages_and_recognises_licence_articles(): void {
        $http = $this->fakePages();
        $orgId = (int) $this->organization->id;

        $result = app(ArticleMirror::class)->sync($orgId, $this->source());

        $http->assertSentCount(2);
        $this->assertSame(['read' => 3, 'created' => 3, 'updated' => 0, 'archived' => 0, 'relinked' => 0], $result, 'Textpositionen sind keine Artikel');
        $this->assertSame(3, AccountingArticle::query()->where('plugin_id', EasybillPlugin::ID)->count());
        // Bruttopreis 119,00 bei 19 % → netto 100,0000.
        $this->assertSame('100.0000', AccountingArticle::query()->where('external_id', '12')->sole()->net_unit_price?->getAmount());
        $this->assertNotNull(AccountingArticle::query()->where('external_id', '14')->value('archived_at'), 'archivierter Artikel bleibt gespiegelt, aber inaktiv');

        $active = $this->activeByName();
        $this->assertSame(['Microsoft 365 Business Premium', 'Support-Stunde'], array_keys($active));
        $premium = $active['Microsoft 365 Business Premium'];
        $this->assertSame('MS-BP', $premium->number);
        $this->assertSame('Stück', $premium->unitName);
        $this->assertSame('easybill', $premium->sourceLabel);
        $this->assertSame('247.2000', $premium->netPrice?->getAmount());
        $this->assertSame('service', $premium->itemType);
        $router = app(ArticleCatalog::class)->find($orgId, ArticleCatalog::key('eb', (int) AccountingArticle::query()->where('external_id', '14')->value('id')));
        $this->assertNotNull($router, 'archivierte Artikel bleiben über den Schlüssel erreichbar');
        $this->assertSame('material', $router->itemType, 'easybill PRODUCT wird als Material gebucht');
        $this->assertFalse($router->active);
        $this->assertSame($premium->key, app(ArticleCatalog::class)->fromFormKey($orgId, $premium->formKey)?->key, 'Formularschlüssel dekodiert zur Quelle');

        $classifier = app(LicenseArticleClassifier::class);
        $this->assertSame(ResaleArticleRole::License, $classifier->detected($premium->name));
        $this->assertTrue($classifier->isLicense($orgId, $premium->key, $premium->name));
        $this->assertFalse($classifier->isLicense($orgId, $active['Support-Stunde']->key, 'Support-Stunde'));
    }

    public function test_mirrored_lines_carry_the_catalog_key_and_classify_as_licence(): void {
        $this->fakePages();
        $orgId = (int) $this->organization->id;
        app(ArticleMirror::class)->sync($orgId, $this->source());
        $key = $this->activeByName()['Microsoft 365 Business Premium']->key;

        // Positionen tragen die Artikelnummer (`DocumentPosition.number`), nicht die Artikel-ID.
        $line = $this->storeVoucherWithLine('MS-BP');

        $this->assertSame($key, $line->article_ref);
        $this->assertSame('MS-BP', $line->external_article_id);
        $classifier = app(LicenseArticleClassifier::class);
        $this->assertTrue($classifier->isLicense($orgId, $line->article_ref, $line->name));

        ResaleArticleClassification::query()->create(['organization_id' => $orgId, 'article_ref' => $key, 'role' => ResaleArticleRole::Excluded]);
        $this->assertFalse(app(LicenseArticleClassifier::class)->isLicense($orgId, $line->article_ref, $line->name), 'die Einstufung des Betreibers gilt für die Quelle');
    }

    public function test_relinks_lines_mirrored_before_the_catalog(): void {
        $orgId = (int) $this->organization->id;
        $line = $this->storeVoucherWithLine('MS-BP');
        $this->assertSame('easybill:MS-BP', $line->article_ref, 'ohne Katalog nur Plugin und Kennung');

        $this->fakePages();
        $result = app(ArticleMirror::class)->sync($orgId, $this->source());

        $this->assertSame(1, $result['relinked']);
        $this->assertSame($this->activeByName()['Microsoft 365 Business Premium']->key, $line->fresh()?->article_ref);
    }

    public function test_page_limit_is_reported_instead_of_truncating(): void {
        $orgId = (int) $this->organization->id;
        AccountingArticle::query()->create(['organization_id' => $orgId, 'plugin_id' => EasybillPlugin::ID, 'external_id' => '99', 'number' => 'ALT', 'name' => 'Altbestand']);
        $http = FakePluginHttp::fake([
            self::POSITIONS => fn () => FakePluginHttp::response(self::page(1, 999, 9999, array_slice($this->positions(), 0, 2))),
        ]);

        try {
            app(ArticleMirror::class)->sync($orgId, $this->source());
            $this->fail('Seitenlimit muss gemeldet werden');
        } catch (IncompleteRemoteReadException $e) {
            $this->assertSame('page_limit', $e->reason);
        }

        $http->assertSentCount(20);
        $this->assertSame(1, AccountingArticle::query()->count(), 'vor dem vollständigen Lesen wird nichts geschrieben');
        $this->assertNull(AccountingArticle::query()->where('external_id', '99')->value('archived_at'), 'nicht gelesen heißt nicht weg');
    }
}
