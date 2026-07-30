<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegritySecondarySignalsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Release;

use App\Enums\Security\IntegrityCheckStatus;
use App\Models\IntegrityCheck;
use App\Services\Release\{CodeIntegrityService, IntegrityAnchorService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MVP-447 — Sekundärsignale: `.env`-Drift (datensparsam: Datei-Hash +
 * Schlüsselsatz, nie Werte), Git-Sekundär-Check als reines WARN und der
 * externe Anker-Vergleich (Root/Historie) über den BackupTarget-Vertrag.
 */
class IntegritySecondarySignalsTest extends TestCase {
    use RefreshDatabase;

    private string $base = '';

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');

        $this->base = sys_get_temp_dir() . '/wd_integrity_sec_' . uniqid();
        FileFacade::makeDirectory($this->base . '/src', 0775, true);
        file_put_contents($this->base . '/src/a.php', 'alpha');
        file_put_contents($this->base . '/root.txt', 'root');
        file_put_contents($this->base . '/.env', "APP_KEY=abc\nDB_PASSWORD=secret\n");

        config()->set('integrity.base', $this->base);
        config()->set('integrity.paths', ['src']);
        config()->set('integrity.root_files', ['root.txt']);
        config()->set('integrity.exclude', []);
        config()->set('integrity.vendor.enabled', false);
    }

    protected function tearDown(): void {
        FileFacade::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function service(): CodeIntegrityService {
        return app(CodeIntegrityService::class);
    }

    public function test_env_fingerprint_stores_hashes_but_never_values(): void {
        $fingerprint = $this->service()->envFingerprint();

        $this->assertNotNull($fingerprint);
        $this->assertSame(2, $fingerprint['key_count']);
        // Kein Wert und kein Schlüsselname landet im Fingerabdruck.
        $encoded = json_encode($fingerprint);
        $this->assertStringNotContainsString('secret', (string) $encoded);
        $this->assertStringNotContainsString('DB_PASSWORD', (string) $encoded);
    }

    public function test_local_baseline_detects_changed_env_values_and_key_set(): void {
        $manifest = $this->service()->build('local');
        $this->assertNotNull($manifest['env']);

        // Nur der Wert ändert sich → „Schlüsselsatz gleich".
        file_put_contents($this->base . '/.env', "APP_KEY=abc\nDB_PASSWORD=other\n");
        $comparison = $this->service()->compare($manifest);
        $this->assertCount(1, $comparison->env);
        $this->assertStringContainsString('Werte abweichend', $comparison->env[0]);
        $this->assertFalse($comparison->clean());

        // Zusätzlicher Schlüssel → „Schlüsselsatz abweichend".
        file_put_contents($this->base . '/.env', "APP_KEY=abc\nDB_PASSWORD=secret\nNEW_KEY=1\n");
        $comparison = $this->service()->compare($manifest);
        $this->assertStringContainsString('Schlüsselsatz abweichend', $comparison->env[0]);
    }

    public function test_release_baseline_carries_no_env_fingerprint(): void {
        // Release-Baselines kennen die .env der Installation nicht.
        $manifest = $this->service()->build('release');

        $this->assertNull($manifest['env']);
        $this->assertSame([], $this->service()->compare($manifest)->env);
    }

    public function test_findings_hash_ignores_warnings_but_covers_env(): void {
        $withWarning = new \App\Services\Release\IntegrityComparison(warnings: ['git dirty']);
        $withoutWarning = new \App\Services\Release\IntegrityComparison();
        $withEnv = new \App\Services\Release\IntegrityComparison(env: ['.env geändert']);

        $this->assertSame($withoutWarning->findingsHash(), $withWarning->findingsHash());
        $this->assertNotSame($withoutWarning->findingsHash(), $withEnv->findingsHash());
        // Reine Warnungen sind nie Fail-Ursache.
        $this->assertTrue($withWarning->clean());
        $this->assertFalse($withEnv->clean());
    }

    public function test_git_check_stays_silent_without_git_directory(): void {
        $manifest = $this->service()->build('local');

        $this->assertSame([], $this->service()->compare($manifest)->warnings);
    }

    public function test_anchor_payload_contains_only_hashes_and_status(): void {
        $manifest = $this->service()->build('local');
        $check = IntegrityCheck::query()->create([
            'ran_at' => now(),
            'status' => IntegrityCheckStatus::Ok,
            'baseline_source' => 'local',
            'baseline_root' => (string) $manifest['root'],
            'files_checked' => 2,
            'findings_hash' => 'abc123',
            'triggered_by' => 'cli',
        ]);

        $payload = app(IntegrityAnchorService::class)->payload($manifest, $check);

        $this->assertSame(IntegrityAnchorService::SCHEMA, $payload['schema']);
        $this->assertSame((string) $manifest['root'], $payload['root']);
        $this->assertSame('abc123', $payload['last_findings_hash']);
        $this->assertSame('ok', $payload['last_status']);
        $this->assertArrayNotHasKey('files', $payload);
    }

    public function test_anchor_comparison_is_skipped_without_backup_target(): void {
        $manifest = $this->service()->build('local');

        $result = app(IntegrityAnchorService::class)->compare($manifest);

        $this->assertSame('skipped', $result['state']);
        $this->assertSame([], $result['issues']);
    }
}
