<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingArticleMirrorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Models\Finance\{AccountingArticle, AccountingVoucherLine};
use App\Services\Finance\Accounting\Articles\{AccountingArticleCatalogSource, ArticleMirror, MirroredArticle};
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, MirroredVoucherLine, VoucherMirror};
use App\Services\Platform\Catalog\ArticleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Artikelspiegel der Buchhaltungsprogramme (Feature 185, gap-09): Anlegen,
 * Aktualisieren, Archivieren, Schlüsselauflösung und Nachziehen der
 * Belegpositionen — mit einer Testquelle, unabhängig vom Programm.
 */
class AccountingArticleMirrorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public const PLUGIN = 'artikeltest';

    /** @var list<MirroredArticle> */
    private array $remote = [];

    private AccountingArticleCatalogSource $source;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $test = $this;
        $this->source = new class($test) extends AccountingArticleCatalogSource {
            public function __construct(private readonly AccountingArticleMirrorTest $test) {}

            public function pluginId(): string {
                return AccountingArticleMirrorTest::PLUGIN;
            }

            public function prefix(): string {
                return 'atk';
            }

            public function label(): string {
                return 'Artikeltest';
            }

            public function isConfigured(int $organizationId): bool {
                return true;
            }

            public function fetch(int $organizationId): array {
                return $this->test->remoteArticles();
            }
        };
        app(ArticleCatalog::class)->register($this->source);
    }

    /** @return list<MirroredArticle> */
    public function remoteArticles(): array {
        return $this->remote;
    }

    public function test_sync_creates_updates_archives_and_revives(): void {
        $orgId = (int) $this->organization->id;
        $mirror = app(ArticleMirror::class);
        $this->remote = [
            new MirroredArticle(externalId: '1', name: 'Microsoft 365 Business Premium', number: 'MS-BP', netUnitPrice: '247.2000', vatRate: '19.00'),
            new MirroredArticle(externalId: '2', name: 'Beratung', number: 'BER', netUnitPrice: '120.0000'),
        ];
        $this->assertSame(['read' => 2, 'created' => 2, 'updated' => 0, 'archived' => 0, 'relinked' => 0], $mirror->sync($orgId, $this->source));

        $this->remote = [
            new MirroredArticle(externalId: '1', name: 'Microsoft 365 Business Premium (Jahr)', number: 'MS-BP', netUnitPrice: '247.2000', vatRate: '19.00'),
            new MirroredArticle(externalId: '3', name: 'Domain .de', number: 'DOM', netUnitPrice: '12.0000'),
        ];
        $this->assertSame(['read' => 2, 'created' => 1, 'updated' => 1, 'archived' => 1, 'relinked' => 0], $mirror->sync($orgId, $this->source), 'Beratung fehlt in der Quelle → archiviert');
        $this->assertNotNull(AccountingArticle::query()->where('external_id', '2')->sole()->archived_at);
        $this->assertSame(['Domain .de', 'Microsoft 365 Business Premium (Jahr)'], array_map(static fn ($a) => $a->name, array_values(array_filter(app(ArticleCatalog::class)->active($orgId), static fn ($a) => $a->source === 'atk'))));

        $this->remote[] = new MirroredArticle(externalId: '2', name: 'Beratung', number: 'BER', netUnitPrice: '120.0000');
        $this->assertSame(['read' => 3, 'created' => 0, 'updated' => 1, 'archived' => 0, 'relinked' => 0], $mirror->sync($orgId, $this->source), 'wieder geführt → wieder aktiv');
        $this->assertNull(AccountingArticle::query()->where('external_id', '2')->sole()->archived_at);
        $this->assertSame(['read' => 3, 'created' => 0, 'updated' => 0, 'archived' => 0, 'relinked' => 0], $mirror->sync($orgId, $this->source), 'unverändert zählt nicht als Änderung');
    }

    public function test_keys_prefer_the_external_id_over_a_number_and_stay_per_plugin(): void {
        $orgId = (int) $this->organization->id;
        $mirror = app(ArticleMirror::class);
        $this->remote = [
            new MirroredArticle(externalId: 'X1', name: 'Erster', number: 'N1'),
            new MirroredArticle(externalId: 'N1', name: 'Zweiter', number: 'X9'),
        ];
        $mirror->sync($orgId, $this->source);
        $first = AccountingArticle::query()->where('external_id', 'X1')->sole();
        $second = AccountingArticle::query()->where('external_id', 'N1')->sole();

        $keys = $mirror->keysFor($orgId, self::PLUGIN, ['N1', 'X1', 'X9', 'unbekannt', '']);

        $this->assertEquals([
            'X9' => ArticleCatalog::key('atk', (int) $second->id),
            'N1' => ArticleCatalog::key('atk', (int) $second->id),
            'X1' => ArticleCatalog::key('atk', (int) $first->id),
        ], $keys, 'die Fremd-ID schlägt die Nummer eines anderen Artikels');
        $this->assertSame([], $mirror->keysFor($orgId, 'fremdes_plugin', ['X1']), 'ohne Quelle keine Schlüssel');
        $this->assertSame([], $mirror->keysFor((int) $orgId + 1, self::PLUGIN, ['X1']), 'andere Organisation sieht nichts');
    }

    public function test_command_syncs_articles_and_relinks_lines_mirrored_before(): void {
        $orgId = (int) $this->organization->id;
        $voucherMirror = app(VoucherMirror::class);
        $counters = VoucherMirror::counters();
        $voucherMirror->store($orgId, self::PLUGIN, new MirroredVoucher(externalId: '100', direction: DocumentDirection::Outgoing, kind: DocumentKind::Invoice, number: 'RE-2026-9003', date: '2026-10-01', totalAmount: '294.17'), $counters);
        $voucherMirror->storeLines($orgId, self::PLUGIN, '100', [
            new MirroredVoucherLine(position: 1, name: 'Microsoft 365 Business Premium', quantity: 1.0, unitNet: '247.2000', totalNet: '247.20', externalArticleId: '1'),
            new MirroredVoucherLine(position: 2, name: 'Freitext', quantity: 1.0, unitNet: '10.0000', totalNet: '10.00'),
        ]);
        $this->assertSame([self::PLUGIN . ':1', null], AccountingVoucherLine::query()->orderBy('position')->pluck('article_ref')->all(), 'vor dem Katalog: Plugin und Kennung');

        $this->remote = [new MirroredArticle(externalId: '1', name: 'Microsoft 365 Business Premium', number: 'MS-BP')];
        $command = $this->artisan('accounting:pull-vouchers', ['--organization' => $orgId]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        // Als Variable läuft der Befehl erst beim Destruktor — hier gleich ausführen, die Prüfungen folgen.
        $command
            ->expectsOutputToContain(sprintf('Org %d · %s: 1 Artikel gelesen, 1 neu, 0 aktualisiert, 0 archiviert, 1 Positionen verknüpft', $orgId, self::PLUGIN))
            ->assertSuccessful()
            ->run();

        $article = AccountingArticle::query()->where('external_id', '1')->sole();
        $this->assertSame([ArticleCatalog::key('atk', (int) $article->id), null], AccountingVoucherLine::query()->orderBy('position')->pluck('article_ref')->all());

        // Neue Positionen tragen den Schlüssel sofort, auch wenn die Kennung die Nummer ist.
        $voucherMirror->storeLines($orgId, self::PLUGIN, '100', [
            new MirroredVoucherLine(position: 1, name: 'Microsoft 365 Business Premium', quantity: 1.0, unitNet: '247.2000', totalNet: '247.20', externalArticleId: 'MS-BP'),
        ]);
        $this->assertSame(ArticleCatalog::key('atk', (int) $article->id), AccountingVoucherLine::query()->sole()->article_ref);
    }
}
