<?php

namespace Tests\Feature\Plugins;

use App\Models\LexofficeArticle;
use App\Plugins\Lexoffice\LexofficeArticleSync;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LexofficeArticleSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
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
}
