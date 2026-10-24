<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeItemStatus, CloudIntakeProvider, CloudIntakeRouteTarget};
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem, CloudDocumentRoute};
use App\Models\Document\Document;
use App\Models\Platform\User;
use App\Plugins\PluginManager;
use App\Plugins\Seafile\Exceptions\SeafileInstanceChangedException;
use App\Plugins\Seafile\SeafilePlugin;
use App\Plugins\Support\Intake\IntakeItem;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use App\Services\CloudIntake\{CloudIntakeRunner, StaleCheckpointException};
use App\Services\Integration\RecordOrigins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\{FakePluginHttp, FakeSeafileServer};
use Tests\TestCase;

/**
 * Seafile im Cloud-Dokumenteingang (MVP-1346/1347): Verbindung aus den
 * Plugin-Einstellungen, Bibliotheken ohne verschlüsselte, Ordnerscan mit
 * Budget, Datei-ID (Inhalts-Hash) als Identität, „uptodate“ ohne Scan,
 * Download über den Dateiserver-Link, Herkunft mit Link, Abgänge.
 */
class SeafileIntakeTest extends TestCase {
    use FakeSeafileServer;
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://seafile.example.test';

    private const REPO = 'aaaaaaaa-1111-4222-8333-444444444444';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret(SeafilePlugin::ID, ['base_url' => self::BASE . '/', 'api_token' => 'sf-token']);
        $this->admin = $this->orgAdmin();
        app()->instance('currentOrganization', $this->organization);

        $this->seafileRepos = [
            self::REPO => ['name' => 'Buchhaltung', 'encrypted' => false],
            'bbbbbbbb-1111-4222-8333-444444444444' => ['name' => 'Privat', 'encrypted' => true],
        ];
        $this->seafileFile(self::REPO, '/Belege/2026/re-1.pdf', '%PDF-1.4 eins');
        $this->seafileFile(self::REPO, '/Belege/2026/Okt/re-2.pdf', '%PDF-1.4 zwei');
        $this->seafileFile(self::REPO, '/Belege/leer.txt', '');
        $this->seafileFile(self::REPO, '/Sonstiges/notiz.pdf', '%PDF-1.4 notiz');
    }

    private function connection(string $root = '/Belege'): CloudDocumentConnection {
        return CloudDocumentConnection::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'provider' => CloudIntakeProvider::Seafile,
            'name' => 'Seafile',
            'external_account_id' => SelfHostedInstance::key(self::BASE) . ':erika@example.test',
            'container_id' => self::REPO,
            'root_folder_path' => $root,
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    private function route(CloudDocumentConnection $connection, string $pattern = '**'): CloudDocumentRoute {
        return CloudDocumentRoute::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'path_pattern' => $pattern,
            'target' => CloudIntakeRouteTarget::Document,
        ]);
    }

    private function plugin(): SeafilePlugin {
        $plugin = app(PluginManager::class)->find(SeafilePlugin::ID);
        $this->assertInstanceOf(SeafilePlugin::class, $plugin);

        return $plugin;
    }

    /** @return array{status: string, pages: int, imported: int, inbox: int, rejected: int, duplicates: int, skipped: int, tombstones: int} */
    private function intake(CloudDocumentConnection $connection): array {
        return app(CloudIntakeRunner::class)->run($connection->refresh());
    }

    private function listings(FakePluginHttp $http): int {
        return count(array_filter($http->recorded(), static fn (array $call): bool => str_ends_with($call['request']->getUri()->getPath(), '/dir/')
            && ! str_contains(urldecode($call['request']->getUri()->getQuery()), 'oid=')));
    }

    public function test_connect_checks_the_account_and_lists_unencrypted_libraries(): void {
        $http = $this->seafileServe(self::BASE);
        $this->actingAs($this->admin)->get(route('admin.cloud-intake.seafile.connect-form'))->assertOk()
            ->assertSee('seafile.example.test')
            ->assertSee(route('admin.cloud-intake.seafile.connect'), false);

        $this->post(route('admin.cloud-intake.seafile.connect'), ['name' => 'Ablage'])
            ->assertRedirect(route('admin.cloud-intake.index'))->assertSessionHas('success');

        $connection = CloudDocumentConnection::query()->sole();
        $this->assertSame(CloudIntakeProvider::Seafile, $connection->provider);
        $this->assertSame(SelfHostedInstance::key(self::BASE) . ':erika@example.test', $connection->external_account_id);
        $this->assertSame('Erika Muster @ seafile.example.test', $connection->external_account_label);
        $this->assertSame(CloudIntakeConnectionStatus::Draft, $connection->status);
        $this->assertNull($connection->access_token, 'Der Token bleibt in den Plugin-Einstellungen.');
        $http->assertSent(static fn (RequestInterface $request): bool => $request->getHeaderLine('Authorization') === 'Token sf-token');

        $containers = $this->plugin()->intakeContainers($connection, null);
        $this->assertSame([self::REPO], array_map(static fn ($c): string => $c->id, $containers), 'Verschlüsselte Bibliothek fehlt, Gruppenfreigabe zählt einmal.');
    }

    public function test_cycle_scans_folders_with_a_budget_and_takes_content_ids(): void {
        config()->set('plugins.seafile.scan_folder_budget', 2);
        $connection = $this->connection();
        $this->seafileServe(self::BASE);

        $first = $this->plugin()->intakeChanges($connection, null);
        $this->assertTrue($first->hasMore, 'Drei Ordner, Budget zwei.');
        $second = $this->plugin()->intakeChanges($connection, $first->checkpoint);
        $this->assertFalse($second->hasMore);

        $items = [...$first->items, ...$second->items];
        $paths = array_map(static fn (IntakeItem $item): string => $item->path, $items);
        sort($paths);
        $this->assertSame(['2026/Okt/re-2.pdf', '2026/re-1.pdf'], $paths, 'Leere Datei und Dateien außerhalb des Stammordners fehlen.');
        $one = array_values(array_filter($items, static fn (IntakeItem $item): bool => $item->path === '2026/re-1.pdf'))[0];
        $this->assertSame(sha1('%PDF-1.4 eins'), $one->itemId);
        $this->assertSame($one->itemId, $one->revision);
    }

    public function test_runner_imports_records_the_origin_and_skips_unchanged_trees(): void {
        $connection = $this->connection();
        $this->route($connection);
        $http = $this->seafileServe(self::BASE);

        $first = $this->intake($connection);
        $this->assertSame('ok', $first['status']);
        $this->assertSame(2, $first['imported']);
        $evidence = CloudDocumentItem::query()->where('external_item_id', sha1('%PDF-1.4 eins'))->sole();
        $document = Document::query()->findOrFail($evidence->imported_id);
        $origins = app(RecordOrigins::class)->for($document);
        $this->assertSame([self::BASE . '/lib/' . self::REPO . '/file/Belege/2026/re-1.pdf'], array_column($origins, 'url'));
        $http->assertSent(static fn (RequestInterface $request): bool => str_starts_with($request->getUri()->getPath(), '/seafhttp/files/')
            && $request->getHeaderLine('Authorization') === '');

        // Nichts geändert: Seafile antwortet „uptodate“, kein Ordner wird gelesen.
        $http = $this->seafileServe(self::BASE);
        $this->assertSame(0, $this->intake($connection)['imported']);
        $this->assertSame(0, $this->listings($http));

        // Umbenennen ändert die Datei-ID nicht: kein neuer Beleg.
        $this->seafileFiles[self::REPO]['/Belege/2026/re-1-umbenannt.pdf'] = $this->seafileFiles[self::REPO]['/Belege/2026/re-1.pdf'];
        unset($this->seafileFiles[self::REPO]['/Belege/2026/re-1.pdf']);
        $this->seafileServe(self::BASE);
        $result = $this->intake($connection);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['tombstones']);

        unset($this->seafileFiles[self::REPO]['/Belege/2026/Okt/re-2.pdf']);
        $this->seafileServe(self::BASE);
        $this->assertSame(1, $this->intake($connection)['tombstones']);
        $this->assertSame(CloudIntakeItemStatus::SourceGone, CloudDocumentItem::query()->where('external_item_id', sha1('%PDF-1.4 zwei'))->sole()->status);
    }

    public function test_missing_root_another_instance_and_stale_checkpoint_fail(): void {
        $connection = $this->connection('/Gibt-es-nicht');
        $this->route($connection);
        $this->seafileServe(self::BASE);
        $this->assertSame('failed', $this->intake($connection)['status']);
        $this->assertSame(0, CloudDocumentItem::query()->count(), 'Ein fehlender Stammordner ist kein „alles gelöscht“.');

        $connection->forceFill(['root_folder_path' => '/Belege'])->save();
        $this->pluginSecret(SeafilePlugin::ID, ['base_url' => 'https://anderes.example.test']);
        $this->seafileServe('https://anderes.example.test');
        $this->assertSame('failed', $this->intake($connection)['status']);
        $this->assertSame(class_basename(SeafileInstanceChangedException::class), $connection->refresh()->last_error);

        $this->expectException(StaleCheckpointException::class);
        $this->pluginSecret(SeafilePlugin::ID, ['base_url' => self::BASE]);
        $this->plugin()->intakeChanges($connection->refresh(), '{"v":0}');
    }

    public function test_a_removed_library_mid_cycle_reports_no_departures(): void {
        config()->set('plugins.seafile.scan_folder_budget', 1);
        $connection = $this->connection();
        $this->route($connection);
        $this->seafileServe(self::BASE);
        $this->intake($connection);
        $imported = CloudDocumentItem::query()->where('status', CloudIntakeItemStatus::Imported->value)->count();
        $this->assertGreaterThan(0, $imported);

        // Neuer Zyklus: Stammordner geändert, dann verschwindet die Bibliothek zwischen zwei Seiten.
        $this->seafileFile(self::REPO, '/Belege/neu.pdf', '%PDF-1.4 neu');
        $this->seafileServe(self::BASE);
        $first = $this->plugin()->intakeChanges($connection->refresh(), $connection->checkpoint);
        $this->assertTrue($first->hasMore);
        unset($this->seafileRepos[self::REPO]);
        $this->seafileServe(self::BASE);

        $this->expectException(\RuntimeException::class);
        $this->plugin()->intakeChanges($connection, $first->checkpoint);
    }

    public function test_health_reports_setup_and_reachability(): void {
        $plugin = $this->plugin();
        $this->seafileServe(self::BASE);
        $this->assertSame('not_connected', $plugin->healthCheck()->code);

        $this->connection();
        $this->assertSame('ok', $plugin->healthCheck()->status);
    }
}
