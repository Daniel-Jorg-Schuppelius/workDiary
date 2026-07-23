<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CodeIntegrityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Release;

use App\Enums\Security\IntegrityCheckStatus;
use App\Jobs\Security\{FreezeIntegrityBaselineJob, RunIntegrityCheckJob};
use App\Models\{AuditLog, IntegrityCheck, User};
use App\Notifications\GenericEventNotification;
use App\Services\Release\{CodeIntegrityService, ReleaseManifestService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File as FileFacade, Notification, Queue, Storage};
use Tests\TestCase;

/**
 * Quelltext-Integritätsüberwachung (Feature 095, MVP-439–442): Baseline-
 * Erzeugung (freeze), Tamper-Erkennung (added/modified/deleted/Pakete),
 * Signatur-/Artefaktkette, Exit-Codes, Zustandswechsel-Alarme und Admin-UI.
 */
class CodeIntegrityTest extends TestCase {
    use RefreshDatabase;

    private string $base = '';

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');

        $this->base = sys_get_temp_dir() . '/wd_integrity_' . uniqid();
        FileFacade::makeDirectory($this->base . '/src', 0775, true);
        FileFacade::makeDirectory($this->base . '/src/skip', 0775, true);
        FileFacade::makeDirectory($this->base . '/vendor/acme/pkg', 0775, true);
        file_put_contents($this->base . '/src/a.php', 'alpha');
        file_put_contents($this->base . '/src/b.php', 'beta');
        file_put_contents($this->base . '/src/skip/ignored.php', 'ignored');
        file_put_contents($this->base . '/root.txt', 'root');
        file_put_contents($this->base . '/vendor/acme/pkg/lib.php', 'lib');

        config()->set('integrity.base', $this->base);
        config()->set('integrity.paths', ['src']);
        config()->set('integrity.root_files', ['root.txt']);
        config()->set('integrity.exclude', ['src/skip']);
        config()->set('integrity.vendor.enabled', true);
        config()->set('integrity.vendor.path', 'vendor');
    }

    protected function tearDown(): void {
        FileFacade::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function service(): CodeIntegrityService {
        return app(CodeIntegrityService::class);
    }

    private function platformAdmin(): User {
        return User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_freeze_creates_deterministic_baseline_with_audit_anchor(): void {
        $this->artisan('integrity:freeze --yes')->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists(CodeIntegrityService::STORAGE_PATH));
        $manifest = json_decode((string) Storage::disk('local')->get(CodeIntegrityService::STORAGE_PATH), true);
        $this->assertSame(CodeIntegrityService::SCHEMA, $manifest['schema']);
        $this->assertSame('local', $manifest['source']);
        // src/a.php, src/b.php, root.txt — src/skip ist ausgeschlossen.
        $this->assertSame(['root.txt', 'src/a.php', 'src/b.php'], array_column($manifest['files'], 'path'));
        $this->assertSame(['acme/pkg'], array_column($manifest['packages'], 'name'));

        // Determinismus: zweiter Build liefert denselben Root-Hash.
        $again = $this->service()->build('local');
        $this->assertSame($manifest['root'], $again['root']);

        $check = IntegrityCheck::query()->where('status', IntegrityCheckStatus::Baseline->value)->first();
        $this->assertNotNull($check);
        $this->assertSame('local', $check->baseline_source);
        $this->assertSame(1, AuditLog::query()->where('event', 'integrity.freeze')->count());
    }

    public function test_verify_detects_added_modified_deleted_and_package_tampering(): void {
        Notification::fake();
        $admin = $this->platformAdmin();

        $this->service()->freeze('local');
        $clean = $this->service()->runVerification();
        $this->assertSame(IntegrityCheckStatus::Ok, $clean->status);
        Notification::assertNothingSent();

        // Tampern: ändern, hinzufügen, löschen + vendor-Paket manipulieren.
        file_put_contents($this->base . '/src/a.php', 'alpha-tampered');
        file_put_contents($this->base . '/src/evil.php', 'evil');
        unlink($this->base . '/src/b.php');
        file_put_contents($this->base . '/vendor/acme/pkg/lib.php', 'lib-tampered');

        $tampered = $this->service()->runVerification();
        $this->assertSame(IntegrityCheckStatus::Deviation, $tampered->status);
        $this->assertSame(1, $tampered->added_count);
        $this->assertSame(1, $tampered->modified_count);
        $this->assertSame(1, $tampered->deleted_count);
        $this->assertSame(1, $tampered->packages_changed_count);
        $this->assertContains('src/evil.php', $tampered->findings['added']);
        $this->assertContains('src/a.php', $tampered->findings['modified']);
        $this->assertContains('src/b.php', $tampered->findings['deleted']);
        $this->assertContains('acme/pkg', $tampered->findings['packages']);
        Notification::assertSentToTimes($admin, GenericEventNotification::class, 1);

        // Unveränderter Befund → kein Doppel-Alarm.
        $this->service()->runVerification();
        Notification::assertSentToTimes($admin, GenericEventNotification::class, 1);

        // Wiederherstellen → ok + Entwarnung.
        file_put_contents($this->base . '/src/a.php', 'alpha');
        file_put_contents($this->base . '/src/b.php', 'beta');
        unlink($this->base . '/src/evil.php');
        file_put_contents($this->base . '/vendor/acme/pkg/lib.php', 'lib');

        $restored = $this->service()->runVerification();
        $this->assertSame(IntegrityCheckStatus::Ok, $restored->status);
        Notification::assertSentToTimes($admin, GenericEventNotification::class, 2);

        // Jeder Lauf hängt in der Audit-Kette.
        $this->assertSame(4, AuditLog::query()->where('event', 'integrity.check')->count());
    }

    public function test_verify_command_exit_codes(): void {
        // Ohne Baseline → 2.
        $this->artisan('integrity:verify')->assertExitCode(2);

        $this->service()->freeze('local');
        $this->artisan('integrity:verify')->assertExitCode(0);

        file_put_contents($this->base . '/src/a.php', 'tampered');
        $this->artisan('integrity:verify --json')->assertExitCode(1);
    }

    public function test_schedule_trigger_respects_enabled_flag(): void {
        config()->set('integrity.enabled', false);

        $this->artisan('integrity:verify --trigger=schedule')->assertExitCode(0);
        $this->assertSame(0, IntegrityCheck::query()->count());
    }

    public function test_release_manifest_carries_integrity_root_and_chain_detects_manifest_tampering(): void {
        // Baseline + release.json mit Integritäts-Artefakt und Root im Payload.
        $this->service()->freeze('local');
        $release = app(ReleaseManifestService::class)->build();
        $this->assertSame(
            json_decode((string) Storage::disk('local')->get(CodeIntegrityService::STORAGE_PATH), true)['root'],
            $release['integrity']['root'],
        );
        $this->assertContains('integrity', array_column($release['artifacts'], 'name'));
        Storage::disk('local')->put(
            ReleaseManifestService::STORAGE_PATH,
            (string) json_encode($release, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        // Sauberer Lauf: Kette in Ordnung.
        $clean = $this->service()->runVerification();
        $this->assertSame(IntegrityCheckStatus::Ok, $clean->status);

        // integrity.json selbst manipulieren → Artefakt-Hash der Kette schlägt an.
        $manifest = json_decode((string) Storage::disk('local')->get(CodeIntegrityService::STORAGE_PATH), true);
        $manifest['files'][1]['sha256'] = str_repeat('0', 64);
        Storage::disk('local')->put(
            CodeIntegrityService::STORAGE_PATH,
            (string) json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        $tampered = $this->service()->runVerification();
        $this->assertSame(IntegrityCheckStatus::Deviation, $tampered->status);
        $this->assertNotEmpty($tampered->findings['chain'] ?? []);
    }

    public function test_admin_page_is_platform_admin_only_and_dispatches_jobs(): void {
        $admin = $this->platformAdmin();
        $orgUser = User::factory()->create();

        $this->actingAs($orgUser)->get(route('admin.integrity.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.integrity.index'))
            ->assertOk()
            ->assertSee('Quelltext-Integrität');

        Queue::fake();
        $this->actingAs($admin)->post(route('admin.integrity.verify'))->assertRedirect(route('admin.integrity.index'));
        Queue::assertPushed(RunIntegrityCheckJob::class);

        $this->actingAs($admin)->post(route('admin.integrity.freeze'))->assertRedirect(route('admin.integrity.index'));
        Queue::assertPushed(FreezeIntegrityBaselineJob::class);

        $this->actingAs($orgUser)->post(route('admin.integrity.verify'))->assertForbidden();
    }
}
