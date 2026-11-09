<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeArticleSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\Integration\PendingExternalConflict;
use App\Plugins\Lexoffice\Models\LexofficeArticle;
use App\Plugins\Lexoffice\Services\LexofficeArticleSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Lexoffice-Artikel als Projektion (nur lesend). Das Rückschreiben samt
 * Artikelkonflikten ist entfallen (MVP-1519): niemand markierte einen Artikel
 * als lokal geändert.
 */
class LexofficeArticleSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key']);
    }

    private function localArticle(int $version = 1): LexofficeArticle {
        return LexofficeArticle::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'lex-8',
            'external_version' => $version,
            'name' => 'Lokaler Stand',
            'type' => 'SERVICE',
            'currency' => 'EUR',
            'net_unit_price' => '100.00',
            'vat_rate' => '19',
        ]);
    }

    private function fakeArticleList(int $version = 2): void {
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/articles*' => FakePluginHttp::response(['content' => [[
                'id' => 'lex-8', 'version' => $version, 'title' => 'Stand in Lexoffice',
                'type' => 'SERVICE', 'price' => ['netPrice' => 120.00, 'leadingPrice' => 'NET', 'taxRate' => 19.0],
            ]], 'totalPages' => 1], 200),
        ]);
    }

    public function test_newer_lexoffice_version_overwrites_the_projection_without_conflicts(): void {
        $local = $this->localArticle();
        $this->fakeArticleList();

        $result = (new LexofficeArticleSync($this->organization->id))->sync($this->organization);

        $this->assertSame(1, $result['updated']);
        $this->assertArrayNotHasKey('conflicts', $result);
        $this->assertSame('Stand in Lexoffice', $local->fresh()?->name);
        $this->assertSame(2, $local->fresh()?->external_version);
        $this->assertSame(0, PendingExternalConflict::query()->count());
    }

    public function test_same_version_is_not_rewritten(): void {
        $local = $this->localArticle(2);
        $this->fakeArticleList(2);

        $result = (new LexofficeArticleSync($this->organization->id))->sync($this->organization);

        $this->assertSame(0, $result['updated']);
        $this->assertSame('Lokaler Stand', $local->fresh()?->name);
    }

    public function test_command_sync_reports_without_conflict_counter(): void {
        $this->localArticle();
        $this->fakeArticleList();

        $this->artisan('lexoffice:sync-articles', ['--organization' => $this->organization->id])
            ->expectsOutputToContain('created: 0, updated: 1, archived: 0')
            ->assertSuccessful();
    }

    public function test_sync_creates_articles_from_paginated_response(): void {
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/articles*' => [
                FakePluginHttp::response([
                    'content' => [
                        [
                            'id' => 'lex-1',
                            'title' => 'Beratung',
                            'type' => 'SERVICE',
                            'unitName' => 'Stunde',
                            'price' => ['netPrice' => 100.00, 'currency' => 'EUR', 'taxRate' => 19.0],
                        ],
                        [
                            'id' => 'lex-2',
                            'title' => 'Switch 24-Port',
                            'type' => 'PRODUCT',
                            'unitName' => 'Stk',
                            'articleNumber' => 'SW24',
                            'price' => ['netPrice' => 250.00, 'currency' => 'EUR', 'taxRate' => 19.0],
                        ],
                    ],
                    'totalPages' => 2,
                ], 200),
                FakePluginHttp::response([
                    'content' => [
                        [
                            'id' => 'lex-3',
                            'title' => 'Reisezeit',
                            'type' => 'SERVICE',
                            'unitName' => 'Stunde',
                            'price' => ['netPrice' => 60.00, 'currency' => 'EUR', 'taxRate' => 19.0],
                        ],
                    ],
                    'totalPages' => 2,
                ], 200),
            ],
        ]);

        $sync = new LexofficeArticleSync($this->organization->id);
        $result = $sync->sync($this->organization);

        $this->assertSame(3, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['archived']);
        $this->assertSame(3, LexofficeArticle::count());
        $this->assertDatabaseHas('lexoffice_articles', [
            'external_id' => 'lex-2',
            'name' => 'Switch 24-Port',
            'article_number' => 'SW24',
            'type' => 'PRODUCT',
        ]);
        // Blättern nach totalPages wie zuvor: genau zwei Seiten.
        $this->assertSame(
            ['https://api.lexware.io/v1/articles?page=0&size=100', 'https://api.lexware.io/v1/articles?page=1&size=100'],
            array_map(static fn (array $entry): string => (string) $entry['request']->getUri(), $fake->recorded()),
        );
    }

    public function test_sync_archives_missing_articles_and_is_idempotent(): void {
        $callCount = 0;
        FakePluginHttp::fake([
            '*' => function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return FakePluginHttp::response([
                        'content' => [
                            ['id' => 'lex-1', 'title' => 'A', 'type' => 'SERVICE', 'price' => ['netPrice' => 1, 'taxRate' => 19]],
                            ['id' => 'lex-2', 'title' => 'B', 'type' => 'SERVICE', 'price' => ['netPrice' => 2, 'taxRate' => 19]],
                        ],
                        'totalPages' => 1,
                    ], 200);
                }

                return FakePluginHttp::response([
                    'content' => [
                        ['id' => 'lex-1', 'title' => 'A neu', 'type' => 'SERVICE', 'price' => ['netPrice' => 1.5, 'taxRate' => 19]],
                    ],
                    'totalPages' => 1,
                ], 200);
            },
        ]);

        $sync = new LexofficeArticleSync($this->organization->id);
        $sync->sync($this->organization);
        $this->assertSame(2, LexofficeArticle::active()->count());

        $result = $sync->sync($this->organization);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['archived']);
        $this->assertSame(1, LexofficeArticle::active()->count());
        $this->assertDatabaseHas('lexoffice_articles', [
            'external_id' => 'lex-1',
            'name' => 'A neu',
        ]);
        $this->assertNotNull(LexofficeArticle::where('external_id', 'lex-2')->first()?->archived_at);
    }
}
