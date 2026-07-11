<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeArticleMatchingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Article, ArticleVariant, IntegrationInboxItem};
use App\Plugins\Lexoffice\LexofficeArticleSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 048/078: Stammdaten-Brücke Lexoffice ↔ lokaler Artikelstamm über
 * den gemeinsamen VariantMatcher — Artikelnummer (SKU) primär, eindeutige
 * GTIN als systemübergreifender Schlüssel. Kein Treffer bleibt bewusst
 * reine Projektion (Dienstleistungen); mehrdeutige GTIN geht in die
 * Integrations-Inbox.
 */
final class LexofficeArticleMatchingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_sync_links_articles_by_sku_and_gtin_and_inboxes_ambiguous(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $bySku = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'sku' => 'SW24',
            'option_signature' => 'sig-sw24',
        ]);
        $byGtin = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'sku' => 'KABEL-5M',
            'option_signature' => 'sig-kabel-5m',
            'gtin' => '4001234567890',
        ]);
        // Zwei Varianten mit derselben GTIN → mehrdeutig.
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'sku' => 'DUP-A',
            'option_signature' => 'sig-dup-a',
            'gtin' => '4009999999999',
        ]);
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'sku' => 'DUP-B',
            'option_signature' => 'sig-dup-b',
            'gtin' => '4009999999999',
        ]);

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles*' => FakePluginHttp::response([
                'content' => [
                    [
                        'id' => 'lex-sku',
                        'title' => 'Switch 24-Port',
                        'type' => 'product',
                        'articleNumber' => 'SW24',
                        'price' => ['netPrice' => 250.00, 'currency' => 'EUR', 'taxRate' => 19.0],
                    ],
                    [
                        'id' => 'lex-gtin',
                        'title' => 'Kabel 5m',
                        'type' => 'product',
                        'articleNumber' => 'ANDERE-NUMMER',
                        'gtin' => '4001234567890',
                        'price' => ['netPrice' => 9.90, 'currency' => 'EUR', 'taxRate' => 19.0],
                    ],
                    [
                        'id' => 'lex-ambiguous',
                        'title' => 'Duplikat-GTIN',
                        'type' => 'product',
                        'gtin' => '4009999999999',
                        'price' => ['netPrice' => 5.00, 'currency' => 'EUR', 'taxRate' => 19.0],
                    ],
                    [
                        'id' => 'lex-service',
                        'title' => 'Beratung',
                        'type' => 'service',
                        'unitName' => 'Stunde',
                        'price' => ['netPrice' => 100.00, 'currency' => 'EUR', 'taxRate' => 19.0],
                    ],
                ],
                'totalPages' => 1,
            ]),
        ]);

        $result = (new LexofficeArticleSync('test-key'))->sync($this->organization);

        $this->assertSame(4, $result['created']);
        $this->assertSame(2, $result['linked']);
        $this->assertSame(1, $result['ambiguous']);

        // SKU-Treffer.
        $this->assertDatabaseHas('external_article_mappings', [
            'plugin_id' => 'lexoffice',
            'external_id' => 'lex-sku',
            'article_variant_id' => $bySku->id,
            'sync_status' => 'linked',
        ]);
        // GTIN als Brücke trotz abweichender Artikelnummer.
        $this->assertDatabaseHas('external_article_mappings', [
            'plugin_id' => 'lexoffice',
            'external_id' => 'lex-gtin',
            'article_variant_id' => $byGtin->id,
        ]);
        // Mehrdeutige GTIN: kein Mapping, sondern Inbox-Fall.
        $this->assertDatabaseMissing('external_article_mappings', ['external_id' => 'lex-ambiguous']);
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => 'lexoffice',
            'external_id' => 'lex-ambiguous',
            'case_type' => IntegrationInboxItem::CASE_AMBIGUOUS,
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);
        // Dienstleistung ohne SKU/GTIN: reine Projektion, kein Inbox-Rauschen.
        $this->assertDatabaseMissing('external_article_mappings', ['external_id' => 'lex-service']);
        $this->assertDatabaseMissing('integration_inbox_items', ['external_id' => 'lex-service']);
    }

    public function test_resolving_ambiguity_links_on_next_sync_and_closes_inbox_case(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'sku' => 'DUP-A',
            'option_signature' => 'sig-dup-a',
            'gtin' => '4009999999999',
        ]);
        $keeper = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'sku' => 'DUP-B',
            'option_signature' => 'sig-dup-b',
            'gtin' => '4009999999999',
        ]);

        $stub = FakePluginHttp::response([
            'content' => [[
                'id' => 'lex-dup',
                'title' => 'Duplikat-GTIN',
                'type' => 'product',
                'gtin' => '4009999999999',
                'price' => ['netPrice' => 5.00, 'currency' => 'EUR', 'taxRate' => 19.0],
            ]],
            'totalPages' => 1,
        ]);

        FakePluginHttp::fake(['https://api.lexoffice.io/v1/articles*' => $stub]);
        (new LexofficeArticleSync('test-key'))->sync($this->organization);
        $this->assertDatabaseHas('integration_inbox_items', [
            'external_id' => 'lex-dup',
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);

        // Datenqualität behoben: GTIN-Dublette bereinigt → nächster Lauf
        // verknüpft und schließt den Inbox-Fall idempotent.
        ArticleVariant::query()->where('sku', 'DUP-A')->update(['gtin' => null]);

        FakePluginHttp::fake(['https://api.lexoffice.io/v1/articles*' => $stub]);
        $result = (new LexofficeArticleSync('test-key'))->sync($this->organization);

        $this->assertSame(1, $result['linked']);
        $this->assertDatabaseHas('external_article_mappings', [
            'plugin_id' => 'lexoffice',
            'external_id' => 'lex-dup',
            'article_variant_id' => $keeper->id,
        ]);
        $this->assertDatabaseHas('integration_inbox_items', [
            'external_id' => 'lex-dup',
            'status' => IntegrationInboxItem::STATUS_RESOLVED_LINKED,
        ]);
    }
}
