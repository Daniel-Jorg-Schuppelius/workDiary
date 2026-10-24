<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileBackupTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Models\Backup\BackupTargetConnection;
use App\Models\Platform\User;
use App\Plugins\PluginManager;
use App\Plugins\Seafile\SeafilePlugin;
use App\Services\Backup\BackupNaming;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeSeafileServer;
use Tests\TestCase;

/**
 * Seafile als Backupziel (MVP-1348): nur Plattform-Admin, Bibliothek über
 * Namen, keine verschlüsselte, Backupbereich und Selbsttest beim Verbinden,
 * Upload über den Dateiserver-Link mit Größenprüfung, Liste mit Ordnern,
 * Löschen von Ordnern samt Inhalt.
 */
class SeafileBackupTest extends TestCase {
    use FakeSeafileServer;
    use RefreshDatabase;
    use WithOrganization;

    private const BASE = 'https://seafile.example.test';

    private const REPO = 'cccccccc-1111-4222-8333-444444444444';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seafileRepos = [
            self::REPO => ['name' => 'WorkDiary-Backup', 'encrypted' => false],
            'dddddddd-1111-4222-8333-444444444444' => ['name' => 'Tresor', 'encrypted' => true],
            'eeeeeeee-1111-4222-8333-444444444444' => ['name' => 'Nur lesen', 'encrypted' => false, 'permission' => 'r', 'own' => false],
            'ffffffff-1111-4222-8333-444444444444' => ['name' => 'Doppelt', 'encrypted' => false],
            'abababab-1111-4222-8333-444444444444' => ['name' => 'doppelt', 'encrypted' => false, 'own' => false],
            'cdcdcdcd-1111-4222-8333-444444444444' => ['name' => 'Team-Ablage', 'encrypted' => false, 'own' => false],
        ];
    }

    private function platformAdmin(): User {
        return User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
    }

    /** @return array<string, string> */
    private function form(string $library = 'workdiary-backup'): array {
        return ['name' => 'Seafile-Sicherung', 'server_url' => self::BASE, 'api_token' => 'sf-backup-token', 'library' => $library];
    }

    private function plugin(): SeafilePlugin {
        $plugin = app(PluginManager::class)->find(SeafilePlugin::ID);
        $this->assertInstanceOf(SeafilePlugin::class, $plugin);

        return $plugin;
    }

    public function test_connect_resolves_the_library_creates_the_area_and_probes_it(): void {
        $http = $this->seafileServe(self::BASE, 'backup@example.test');

        $this->actingAs($this->platformAdmin())->get(route('admin.backup-targets.seafile.connect-form'))->assertOk()
            ->assertSee(route('admin.backup-targets.seafile.connect'), false);
        $this->post(route('admin.backup-targets.seafile.connect'), $this->form())
            ->assertRedirect(route('admin.backup-targets.index'))->assertSessionHas('success');

        $connection = BackupTargetConnection::query()->where('provider', BackupProvider::Seafile->value)->sole();
        $this->assertSame(BackupTargetStatus::Active, $connection->status);
        $this->assertSame(self::REPO, $connection->options['library_id'] ?? null, 'Name ohne Rücksicht auf Groß- und Kleinschreibung.');
        $this->assertSame('kontakt@example.test @ seafile.example.test · WorkDiary-Backup', $connection->external_account_label, 'Angezeigt wird die Kontaktadresse, nicht die interne Kennung.');
        $this->assertNull($connection->quota_total, 'Unbegrenzt meldet Seafile negativ — das bleibt unbekannt.');
        $this->assertSame(1234, $connection->quota_used);
        $pseudonym = app(BackupNaming::class)->pseudonym();
        $this->assertSame($pseudonym, $connection->root_folder_ref);
        $this->assertTrue($this->seafileDirs[self::REPO]['/' . $pseudonym] ?? false);
        $this->assertSame([], array_keys($this->seafileFiles[self::REPO] ?? []), 'Die Probedatei ist wieder gelöscht.');
        $http->assertSent(static fn (RequestInterface $request): bool => str_starts_with($request->getUri()->getPath(), '/seafhttp/upload-api/')
            && $request->getHeaderLine('Authorization') === '');
    }

    public function test_unknown_or_encrypted_library_and_org_admins_are_refused(): void {
        $this->seafileServe(self::BASE);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->from(route('admin.backup-targets.index'))
            ->post(route('admin.backup-targets.seafile.connect'), $this->form('Tresor'))->assertSessionHasErrors('library');
        $this->post(route('admin.backup-targets.seafile.connect'), $this->form('Fehlt'))->assertSessionHasErrors('library');
        $this->post(route('admin.backup-targets.seafile.connect'), $this->form('Nur lesen'))
            ->assertSessionHasErrors(['library' => __('seafile::seafile.backup.library_read_only')]);
        $this->post(route('admin.backup-targets.seafile.connect'), $this->form('Doppelt'))
            ->assertSessionHasErrors(['library' => __('seafile::seafile.backup.library_ambiguous')]);
        $this->assertNotSame(BackupTargetStatus::Active, BackupTargetConnection::query()->where('provider', BackupProvider::Seafile->value)->first()?->status);
        $this->post(route('admin.backup-targets.seafile.connect'), ['server_url' => 'http://seafile.example.test'] + $this->form())->assertSessionHasErrors('server_url');

        $orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($orgAdmin)->post(route('admin.backup-targets.seafile.connect'), $this->form())->assertForbidden();
    }

    public function test_a_shared_library_reports_no_quota_of_its_own(): void {
        $this->seafileServe(self::BASE);
        $this->actingAs($this->platformAdmin())->post(route('admin.backup-targets.seafile.connect'), $this->form('Team-Ablage'))
            ->assertSessionHas('success');

        $connection = BackupTargetConnection::query()->where('provider', BackupProvider::Seafile->value)->sole();
        $this->assertNull($connection->quota_used, 'Bei geteilten Bibliotheken zählt die Quota des Eigentümers.');
    }

    public function test_upload_list_download_and_delete_inside_the_area(): void {
        $this->seafileServe(self::BASE);
        $connection = BackupTargetConnection::factory()->create([
            'provider' => BackupProvider::Seafile,
            'name' => 'Seafile',
            'server_url' => self::BASE,
            'access_token' => 'sf-backup-token',
            'options' => ['library_id' => self::REPO, 'library_own' => true],
        ]);
        $plugin = $this->plugin();

        $this->assertSame('wd-area/gen-1', $plugin->backupEnsureFolder($connection, '/wd-area/gen-1/'));
        $ref = File::withTemp('Chiffrat-Teil-1', static fn (string $path): string => $plugin->backupUploadPart($connection, $path, 'wd-area/gen-1/part-0001.bin'));
        $this->assertSame('wd-area/gen-1/part-0001.bin', $ref);
        $this->assertSame('Chiffrat-Teil-1', (string) $plugin->backupDownload($connection, $ref));

        $listing = $plugin->backupList($connection, 'wd-area');
        $this->assertSame(['gen-1'], array_map(static fn ($object): string => $object->name, $listing), 'Generationsordner erscheinen in der Liste.');
        $this->assertSame([], $plugin->backupList($connection, 'wd-area/fehlt'));

        $this->assertTrue($plugin->backupDelete($connection, 'wd-area/gen-1'));
        $this->assertSame([], array_keys($this->seafileFiles[self::REPO] ?? []), 'Ordner samt Inhalt gelöscht.');
        $this->assertTrue($plugin->backupDelete($connection, 'wd-area/gen-1'), 'Erneutes Löschen ist kein Fehler.');
    }
}
