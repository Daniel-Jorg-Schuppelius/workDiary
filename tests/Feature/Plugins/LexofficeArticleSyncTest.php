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

use App\Models\LexofficeArticle;
use App\Plugins\Lexoffice\LexofficeArticleSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LexofficeArticleSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_sync_creates_articles_from_paginated_response(): void {
        Http::fakeSequence('https://api.lexoffice.io/v1/articles*')
            ->push([
                'content' => [
                    [
                        'id' => 'lex-1',
                        'title' => 'Beratung',
                        'type' => 'service',
                        'unitName' => 'Stunde',
                        'price' => ['netPrice' => 100.00, 'currency' => 'EUR', 'taxRate' => 19.0],
                    ],
                    [
                        'id' => 'lex-2',
                        'title' => 'Switch 24-Port',
                        'type' => 'product',
                        'unitName' => 'Stk',
                        'articleNumber' => 'SW24',
                        'price' => ['netPrice' => 250.00, 'currency' => 'EUR', 'taxRate' => 19.0],
                    ],
                ],
                'totalPages' => 2,
            ], 200)
            ->push([
                'content' => [
                    [
                        'id' => 'lex-3',
                        'title' => 'Reisezeit',
                        'type' => 'service',
                        'unitName' => 'Stunde',
                        'price' => ['netPrice' => 60.00, 'currency' => 'EUR', 'taxRate' => 19.0],
                    ],
                ],
                'totalPages' => 2,
            ], 200);

        $sync = new LexofficeArticleSync('test-key');
        $result = $sync->sync($this->organization);

        $this->assertSame(3, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['archived']);
        $this->assertSame(3, LexofficeArticle::count());
        $this->assertDatabaseHas('lexoffice_articles', [
            'external_id' => 'lex-2',
            'name' => 'Switch 24-Port',
            'article_number' => 'SW24',
            'type' => 'product',
        ]);
    }

    public function test_sync_archives_missing_articles_and_is_idempotent(): void {
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response([
                    'content' => [
                        ['id' => 'lex-1', 'title' => 'A', 'type' => 'service', 'price' => ['netPrice' => 1, 'currency' => 'EUR']],
                        ['id' => 'lex-2', 'title' => 'B', 'type' => 'service', 'price' => ['netPrice' => 2, 'currency' => 'EUR']],
                    ],
                    'totalPages' => 1,
                ], 200);
            }

            return Http::response([
                'content' => [
                    ['id' => 'lex-1', 'title' => 'A neu', 'type' => 'service', 'price' => ['netPrice' => 1.5, 'currency' => 'EUR']],
                ],
                'totalPages' => 1,
            ], 200);
        });

        $sync = new LexofficeArticleSync('test-key');
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

    public function test_push_creates_new_article_via_post_when_external_id_missing(): void {
        Http::fake([
            'https://api.lexoffice.io/v1/articles' => Http::response([
                'id' => 'lex-new', 'version' => 1,
            ], 201),
        ]);

        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id,
            'external_id' => '',
            'name' => 'Neu erstellt',
            'type' => 'service',
            'unit_name' => 'Stunde',
            'net_unit_price' => '90.00',
            'currency' => 'EUR',
            'vat_rate' => '19.00',
            'is_dirty' => true,
        ]);

        (new \App\Plugins\Lexoffice\LexofficeArticleSync('test-key'))->push($article);

        $article->refresh();
        $this->assertSame('lex-new', $article->external_id);
        $this->assertSame(1, $article->external_version);
        $this->assertFalse($article->is_dirty);
        $this->assertNotNull($article->last_pushed_at);
    }

    public function test_push_updates_existing_article_via_put_with_version(): void {
        Http::fake([
            'https://api.lexoffice.io/v1/articles/lex-7' => Http::response([
                'id' => 'lex-7', 'version' => 3,
            ], 200),
        ]);

        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'lex-7',
            'external_version' => 2,
            'name' => 'Geändert',
            'type' => 'service',
            'currency' => 'EUR',
            'is_dirty' => true,
        ]);

        (new \App\Plugins\Lexoffice\LexofficeArticleSync('test-key'))->push($article);

        $article->refresh();
        $this->assertSame(3, $article->external_version);
        $this->assertFalse($article->is_dirty);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && $request->url() === 'https://api.lexoffice.io/v1/articles/lex-7'
                && ($request->data()['version'] ?? null) === 2;
        });
    }

    public function test_manual_review_records_article_conflict_when_dirty_and_remote_diverged(): void {
        $local = LexofficeArticle::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'lex-8',
            'external_version' => 1,
            'name' => 'Lokal geändert',
            'type' => 'service',
            'currency' => 'EUR',
            'net_unit_price' => '100.00',
            'is_dirty' => true,
        ]);

        Http::fake([
            'https://api.lexoffice.io/v1/articles*' => Http::response([
                'content' => [[
                    'id' => 'lex-8', 'version' => 2, 'title' => 'Remote geändert',
                    'type' => 'service', 'price' => ['netPrice' => 120.00, 'currency' => 'EUR'],
                ]],
                'totalPages' => 1,
            ], 200),
        ]);

        $sync = (new \App\Plugins\Lexoffice\LexofficeArticleSync('test-key'))
            ->withPolicy(\App\Plugins\Lexoffice\LexofficeMatchPolicy::ManualReview);

        $result = $sync->sync($this->organization);

        $this->assertSame(1, $result['conflicts']);
        $local->refresh();
        $this->assertSame('Lokal geändert', $local->name, 'Local must remain untouched in manual_review');
        $this->assertDatabaseHas('pending_external_conflicts', [
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'conflict_type' => 'article',
            'referenceable_id' => $local->id,
            'external_id' => 'lex-8',
            'status' => \App\Models\PendingExternalConflict::STATUS_OPEN,
        ]);
    }

    public function test_local_wins_keeps_dirty_article_unchanged_but_updates_version(): void {
        $local = LexofficeArticle::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'lex-9',
            'external_version' => 1,
            'name' => 'Lokal-Version',
            'type' => 'service',
            'currency' => 'EUR',
            'is_dirty' => true,
        ]);

        Http::fake([
            'https://api.lexoffice.io/v1/articles*' => Http::response([
                'content' => [[
                    'id' => 'lex-9', 'version' => 5, 'title' => 'Remote-Version',
                    'type' => 'service', 'price' => ['netPrice' => 50.0, 'currency' => 'EUR'],
                ]],
                'totalPages' => 1,
            ], 200),
        ]);

        $sync = (new \App\Plugins\Lexoffice\LexofficeArticleSync('test-key'))
            ->withPolicy(\App\Plugins\Lexoffice\LexofficeMatchPolicy::LocalWins);

        $sync->sync($this->organization);

        $local->refresh();
        $this->assertSame('Lokal-Version', $local->name);
        $this->assertSame(5, $local->external_version);
    }
}
