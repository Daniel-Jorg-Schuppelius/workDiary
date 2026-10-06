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

use App\Enums\Integration\ExternalConflictStatus;
use App\Enums\User\UserRole;
use App\Models\Integration\PendingExternalConflict;
use App\Models\Platform\User;
use App\Plugins\Lexoffice\Exceptions\LexofficeApiException;
use App\Plugins\Lexoffice\Models\LexofficeArticle;
use App\Plugins\Lexoffice\Services\LexofficeArticleSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class LexofficeArticleSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    // ── Konflikt-Strategie gilt auch für Artikel (Entscheidung 2026-10-06) ──

    private function dirtyLocalArticle(string $externalId = 'lex-8'): LexofficeArticle {
        return LexofficeArticle::create([
            'organization_id' => $this->organization->id,
            'external_id' => $externalId,
            'external_version' => 1,
            'name' => 'Lokal geändert',
            'type' => 'service',
            'currency' => 'EUR',
            'net_unit_price' => '100.00',
            'is_dirty' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function remoteItem(string $externalId = 'lex-8', int $version = 2): array {
        return [
            'id' => $externalId, 'version' => $version, 'title' => 'Remote geändert',
            'type' => 'service', 'price' => ['netPrice' => 120.00, 'currency' => 'EUR'],
        ];
    }

    private function fakeArticleList(): void {
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles*' => FakePluginHttp::response(['content' => [$this->remoteItem()], 'totalPages' => 1], 200),
        ]);
    }

    /** Der Sync aus der Oberfläche las die Einstellung nicht — manuelle Prüfung lief als „Lexoffice gewinnt“. */
    public function test_controller_sync_applies_the_match_policy_from_the_settings(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key', 'match_policy' => 'manual_review']);
        $local = $this->dirtyLocalArticle();
        $this->fakeArticleList();

        $this->actingAs($admin)->post(route('lexoffice.articles.sync'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Sync abgeschlossen: 0 neu, 0 aktualisiert, 0 archiviert. Konflikte zur manuellen Prüfung: 1.');

        $this->assertSame('Lokal geändert', $local->fresh()->name);
        $this->assertDatabaseHas('pending_external_conflicts', ['referenceable_id' => $local->id, 'conflict_type' => 'article', 'status' => 'open']);

        // „Lexoffice gewinnt“ aus der Einstellung überschreibt — und meldet keinen Konflikt.
        $this->pluginSecret('lexoffice', ['match_policy' => 'lexoffice_wins']);
        $this->fakeArticleList();
        $this->actingAs($admin)->post(route('lexoffice.articles.sync'))
            ->assertSessionHas('success', 'Sync abgeschlossen: 0 neu, 1 aktualisiert, 0 archiviert.');
        $this->assertSame('Remote geändert', $local->fresh()->name);
    }

    /** Das Kommando nimmt die Einstellung, `--policy` übersteuert sie — wie beim Kontakt-Kommando. */
    public function test_command_sync_uses_the_setting_and_the_policy_option(): void {
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key', 'match_policy' => 'local_wins']);
        $local = $this->dirtyLocalArticle();
        $this->fakeArticleList();

        $this->artisan('lexoffice:sync-articles', ['--organization' => $this->organization->id])
            ->expectsOutputToContain('[policy=local_wins]')
            ->assertExitCode(0);
        $local->refresh();
        $this->assertSame('Lokal geändert', $local->name);
        $this->assertSame(2, $local->external_version);
        $this->assertSame(0, PendingExternalConflict::query()->count());

        $this->fakeArticleList();
        $this->artisan('lexoffice:sync-articles', ['--organization' => $this->organization->id, '--policy' => 'manual_review'])
            ->expectsOutputToContain('[policy=manual_review]')
            ->expectsOutputToContain('conflicts: 1')
            ->assertExitCode(0);
        $this->assertSame('Lokal geändert', $local->fresh()->name);
        $this->assertSame(1, PendingExternalConflict::query()->where('referenceable_id', $local->id)->count());
    }

    public function test_adopt_remote_fetches_the_article_and_clears_the_dirty_flag(): void {
        $local = $this->dirtyLocalArticle();
        $fake = FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles/lex-8' => FakePluginHttp::response($this->remoteItem(version: 3), 200),
        ]);

        $adopted = (new LexofficeArticleSync('test-key'))->adoptRemote($local);

        $fake->assertSentCount(1);
        $this->assertSame($local->id, $adopted->id);
        $local->refresh();
        $this->assertSame('Remote geändert', $local->name);
        $this->assertSame('120.0000', $local->net_unit_price?->getAmount());
        $this->assertSame(3, $local->external_version);
        $this->assertFalse($local->is_dirty);
        $this->assertNotNull($local->synced_at);
    }

    public function test_adopt_remote_reports_lexoffice_errors_and_leaves_the_article_untouched(): void {
        $local = $this->dirtyLocalArticle();
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles/lex-8' => FakePluginHttp::response(['message' => 'not found'], 404),
        ]);

        try {
            (new LexofficeArticleSync('test-key'))->adoptRemote($local);
            $this->fail('Ein Fehler von Lexoffice muss durchschlagen.');
        } catch (LexofficeApiException $e) {
            $this->assertStringContainsString('404', $e->getMessage());
        }

        $local->refresh();
        $this->assertSame('Lokal geändert', $local->name);
        $this->assertTrue($local->is_dirty);
    }

    /** Ende-zu-Ende: Konflikt aus dem Sync, „Lexoffice-Stand übernehmen“ in der Konfliktliste des Lagers über den Beitrag des Plugins. */
    public function test_conflict_list_adopts_the_lexoffice_state_through_the_plugin_handler(): void {
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key', 'match_policy' => 'manual_review']);
        $local = $this->dirtyLocalArticle();
        $this->fakeArticleList();
        (new LexofficeArticleSync('test-key'))->withPolicy(\App\Plugins\Lexoffice\Enums\LexofficeMatchPolicy::ManualReview)->sync($this->organization);
        $conflict = PendingExternalConflict::query()->sole();

        $manager = $this->userWithRole(UserRole::Buchhaltung->value);
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles/lex-8' => FakePluginHttp::response($this->remoteItem(version: 4), 200),
        ]);

        $this->actingAs($manager)->post(route('inventory.conflicts.adopt-remote', $conflict))
            ->assertRedirect(route('inventory.conflicts.index'))
            ->assertSessionHas('success');

        $local->refresh();
        $this->assertSame('Remote geändert', $local->name);
        $this->assertSame(4, $local->external_version);
        $this->assertFalse($local->is_dirty);
        $this->assertSame(ExternalConflictStatus::ResolvedRemote, $conflict->fresh()->status);
    }

    public function test_sync_creates_articles_from_paginated_response(): void {
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles*' => [
                FakePluginHttp::response([
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
                ], 200),
                FakePluginHttp::response([
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
                ], 200),
            ],
        ]);

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
        FakePluginHttp::fake([
            '*' => function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return FakePluginHttp::response([
                        'content' => [
                            ['id' => 'lex-1', 'title' => 'A', 'type' => 'service', 'price' => ['netPrice' => 1, 'currency' => 'EUR']],
                            ['id' => 'lex-2', 'title' => 'B', 'type' => 'service', 'price' => ['netPrice' => 2, 'currency' => 'EUR']],
                        ],
                        'totalPages' => 1,
                    ], 200);
                }

                return FakePluginHttp::response([
                    'content' => [
                        ['id' => 'lex-1', 'title' => 'A neu', 'type' => 'service', 'price' => ['netPrice' => 1.5, 'currency' => 'EUR']],
                    ],
                    'totalPages' => 1,
                ], 200);
            },
        ]);

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
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles' => FakePluginHttp::response([
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

        (new \App\Plugins\Lexoffice\Services\LexofficeArticleSync('test-key'))->push($article);

        $article->refresh();
        $this->assertSame('lex-new', $article->external_id);
        $this->assertSame(1, $article->external_version);
        $this->assertFalse($article->is_dirty);
        $this->assertNotNull($article->last_pushed_at);
    }

    public function test_push_updates_existing_article_via_put_with_version(): void {
        $fake = FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles/lex-7' => FakePluginHttp::response([
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

        (new \App\Plugins\Lexoffice\Services\LexofficeArticleSync('test-key'))->push($article);

        $article->refresh();
        $this->assertSame(3, $article->external_version);
        $this->assertFalse($article->is_dirty);

        $fake->assertSent(function (RequestInterface $request): bool {
            $data = json_decode((string) $request->getBody(), true);

            return strtoupper($request->getMethod()) === 'PUT'
                && (string) $request->getUri() === 'https://api.lexoffice.io/v1/articles/lex-7'
                && ($data['version'] ?? null) === 2;
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

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles*' => FakePluginHttp::response([
                'content' => [[
                    'id' => 'lex-8', 'version' => 2, 'title' => 'Remote geändert',
                    'type' => 'service', 'price' => ['netPrice' => 120.00, 'currency' => 'EUR'],
                ]],
                'totalPages' => 1,
            ], 200),
        ]);

        $sync = (new \App\Plugins\Lexoffice\Services\LexofficeArticleSync('test-key'))
            ->withPolicy(\App\Plugins\Lexoffice\Enums\LexofficeMatchPolicy::ManualReview);

        $result = $sync->sync($this->organization);

        $this->assertSame(1, $result['conflicts']);
        $local->refresh();
        $this->assertSame('Lokal geändert', $local->name, 'Local must remain untouched in manual_review');
        $this->assertDatabaseHas('pending_external_conflicts', [
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'conflict_type' => 'article',
            'referenceable_id' => $local->id,
            'external_id' => 'lex-8',
            'status' => \App\Enums\Integration\ExternalConflictStatus::Open->value,
        ]);

        // Beide Stände in derselben Form — die Konfliktliste zeigt sie nebeneinander.
        $conflict = \App\Models\Integration\PendingExternalConflict::query()->sole();
        $this->assertSame(['name', 'net_unit_price'], $conflict->diff_fields);
        $this->assertSame('Lokal geändert', $conflict->local_snapshot['name']);
        $this->assertSame('100.0000', $conflict->local_snapshot['net_unit_price']);
        $this->assertSame('Remote geändert', $conflict->remote_snapshot['name']);
        $this->assertSame('120.0000', $conflict->remote_snapshot['net_unit_price']);
        $this->assertSame(2, $conflict->remote_snapshot['external_version']);
        $this->assertSame(array_keys($conflict->local_snapshot), array_values(array_diff(array_keys($conflict->remote_snapshot), ['external_version'])));
    }

    /** „100" aus der API gegen „100.0000 EUR" aus dem Wertobjekt galt als Abweichung — jeder Artikel mit Preis lief in den Konflikt. */
    public function test_manual_review_compares_price_and_tax_rate_by_value(): void {
        $attributes = [
            'organization_id' => $this->organization->id,
            'external_version' => 1,
            'type' => 'service',
            'currency' => 'EUR',
            'net_unit_price' => '100.00',
            'vat_rate' => '19',
            'is_dirty' => true,
        ];
        LexofficeArticle::create(['external_id' => 'lex-10', 'name' => 'Unverändert'] + $attributes);
        LexofficeArticle::create(['external_id' => 'lex-11', 'name' => 'Nur der Satz weicht ab'] + $attributes);

        $remote = fn (string $id, string $title, float $taxRate): array => [
            'id' => $id, 'version' => 2, 'title' => $title, 'type' => 'service',
            'price' => ['netPrice' => 100.0, 'currency' => 'EUR', 'taxRate' => $taxRate],
        ];
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles*' => FakePluginHttp::response([
                'content' => [$remote('lex-10', 'Unverändert', 19), $remote('lex-11', 'Nur der Satz weicht ab', 7)],
                'totalPages' => 1,
            ], 200),
        ]);

        $result = (new \App\Plugins\Lexoffice\Services\LexofficeArticleSync('test-key'))
            ->withPolicy(\App\Plugins\Lexoffice\Enums\LexofficeMatchPolicy::ManualReview)
            ->sync($this->organization);

        $this->assertSame(1, $result['conflicts']);
        $conflict = \App\Models\Integration\PendingExternalConflict::query()->sole();
        $this->assertSame('lex-11', $conflict->external_id);
        $this->assertSame(['vat_rate'], $conflict->diff_fields);
        $this->assertSame('19.00', $conflict->local_snapshot['vat_rate']);
        $this->assertSame('7.00', $conflict->remote_snapshot['vat_rate']);
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

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles*' => FakePluginHttp::response([
                'content' => [[
                    'id' => 'lex-9', 'version' => 5, 'title' => 'Remote-Version',
                    'type' => 'service', 'price' => ['netPrice' => 50.0, 'currency' => 'EUR'],
                ]],
                'totalPages' => 1,
            ], 200),
        ]);

        $sync = (new \App\Plugins\Lexoffice\Services\LexofficeArticleSync('test-key'))
            ->withPolicy(\App\Plugins\Lexoffice\Enums\LexofficeMatchPolicy::LocalWins);

        $sync->sync($this->organization);

        $local->refresh();
        $this->assertSame('Lokal-Version', $local->name);
        $this->assertSame(5, $local->external_version);
    }
}
