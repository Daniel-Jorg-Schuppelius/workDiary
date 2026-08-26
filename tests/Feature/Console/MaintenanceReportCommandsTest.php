<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceReportCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use App\Plugins\Contracts\Plugin;
use App\Plugins\{PluginDefaults, PluginManager};
use App\Services\Install\{EnvWriter, InstallationManager};
use App\Services\Release\ReleaseManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, File, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7 (MVP-725): Prüf-/Berichtskommandos ohne eigenen
 * Test. Alle sind lesend bzw. schreiben nur in Attrappen — getestet wird je
 * Kommando der Normallauf, der Guard-/Fehlerpfad und ein nachweisbarer
 * Seiteneffekt.
 */
class MaintenanceReportCommandsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ── audit:db-hardening-sql ───────────────────────────────────────────

    public function test_db_hardening_sql_prints_a_dba_notice(): void {
        $this->artisan('audit:db-hardening-sql')
            ->expectsOutputToContain('Nicht automatisch ausgeführt')
            ->assertExitCode(0);
    }

    public function test_db_hardening_sql_follows_the_active_driver(): void {
        $driver = DB::connection()->getDriverName();
        $command = $this->artisan('audit:db-hardening-sql', ['--user' => 'wd_app@localhost']);

        if ($driver === 'sqlite') {
            // SQLite kennt keine Rechte — das Kommando MUSS das sagen statt
            // wirkungsloses SQL auszugeben.
            $command->expectsOutputToContain('SQLite kennt keine GRANT/REVOKE-Rechte');
        } else {
            // Eine Zusicherung je Ausgabezeile: zwei Teilstrings derselben
            // Zeile würden einander in der Mockery-Erwartung verdecken.
            $command->expectsOutputToContain("`audit_logs` FROM 'wd_app'@'localhost'");
        }

        $command->assertExitCode(0);
    }

    // ── identifiers:audit ────────────────────────────────────────────────

    public function test_identifier_audit_reports_a_clean_dataset(): void {
        $this->artisan('identifiers:audit')
            ->expectsOutputToContain('beanstandete Identifikatoren')
            ->assertExitCode(0);
    }

    public function test_identifier_audit_writes_a_csv_export(): void {
        $path = storage_path('framework/testing/identifiers-' . bin2hex(random_bytes(5)) . '.csv');

        try {
            $this->artisan('identifiers:audit', ['--csv' => $path])
                ->expectsOutputToContain('CSV geschrieben')
                ->assertExitCode(0);

            $this->assertFileExists($path);
            $this->assertStringStartsWith('model;id;field;value', (string) File::get($path));
        } finally {
            @unlink($path);
        }
    }

    // ── timesheets:duplicates ────────────────────────────────────────────

    public function test_timesheet_duplicates_reports_none_for_clean_data(): void {
        // Ein zweiter OFFENER Zettel je Projekt/Nutzer/Tag ist seit
        // 2027_01_17_100000 durch einen Unique-Index ausgeschlossen — der
        // Befundpfad ist reine Altlast-Diagnose und lässt sich gegen das
        // aktuelle Schema nicht mehr herstellen. Geprüft wird deshalb, dass
        // der Bericht sauber (und ohne SQL-Fehler auf beiden Treibern) läuft.
        $this->artisan('timesheets:duplicates')
            ->expectsOutputToContain('Keine doppelten offenen Stundenzettel gefunden.')
            ->assertExitCode(0);
    }

    // ── plugin:list / plugin:doctor ──────────────────────────────────────

    public function test_plugin_list_shows_registered_plugins(): void {
        $this->registerProbePlugin('probe-list');

        $this->artisan('plugin:list')
            ->expectsOutputToContain('probe-list')
            ->assertExitCode(0);
    }

    public function test_plugin_doctor_passes_for_a_contract_conform_registry(): void {
        $this->registerProbePlugin('probe-ok');

        $this->artisan('plugin:doctor')
            ->expectsOutputToContain('Plugin-Registry ok')
            ->assertExitCode(0);
    }

    public function test_plugin_doctor_fails_on_an_invalid_plugin_id(): void {
        $this->registerProbePlugin('Ungültige ID');

        $this->artisan('plugin:doctor')
            ->expectsOutputToContain('ungültiges ID-Format')
            ->assertExitCode(1);
    }

    // ── app:sqids-salt ───────────────────────────────────────────────────

    public function test_sqids_salt_is_generated_when_missing(): void {
        $envPath = $this->sandboxEnv("APP_NAME=WorkDiary\n");

        $this->artisan('app:sqids-salt')
            ->expectsOutputToContain('SQIDS_SALT erzeugt')
            ->assertExitCode(0);

        $this->assertMatchesRegularExpression('/^SQIDS_SALT=.+$/m', (string) File::get($envPath));
    }

    public function test_sqids_salt_is_never_overwritten_silently(): void {
        $envPath = $this->sandboxEnv("SQIDS_SALT=bestehender-salt\n");

        $this->artisan('app:sqids-salt')
            ->expectsOutputToContain('bereits gesetzt')
            ->assertExitCode(0);

        $this->assertStringContainsString('SQIDS_SALT=bestehender-salt', (string) File::get($envPath));
    }

    public function test_sqids_salt_force_requires_confirmation(): void {
        $envPath = $this->sandboxEnv("SQIDS_SALT=bestehender-salt\n");

        $this->artisan('app:sqids-salt --force')
            ->expectsConfirmation('Wirklich fortfahren?', 'no')
            ->assertExitCode(1);

        $this->assertStringContainsString(
            'SQIDS_SALT=bestehender-salt',
            (string) File::get($envPath),
            'Abgelehnte Rückfrage darf die verteilten Sqid-URLs nicht entwerten.',
        );
    }

    // ── release:verify ───────────────────────────────────────────────────

    public function test_release_verify_reports_a_missing_manifest(): void {
        Storage::fake('local');

        $this->artisan('release:verify')
            ->expectsOutputToContain('Kein Manifest gefunden')
            ->assertExitCode(1);
    }

    public function test_release_verify_rejects_an_unreadable_path(): void {
        $this->artisan('release:verify', ['path' => storage_path('framework/testing/gibt-es-nicht.json')])
            ->expectsOutputToContain('Manifest nicht gefunden')
            ->assertExitCode(1);
    }

    public function test_release_verify_accepts_an_unsigned_manifest_without_artifacts(): void {
        Storage::fake('local');
        Storage::disk('local')->put(ReleaseManifestService::STORAGE_PATH, json_encode(['artifacts' => []]));

        $this->artisan('release:verify')
            ->expectsOutputToContain('Release-Manifest gültig')
            ->assertExitCode(0);
    }

    public function test_release_verify_flags_a_checksum_mismatch(): void {
        Storage::fake('local');
        Storage::disk('local')->put(ReleaseManifestService::STORAGE_PATH, json_encode([
            'artifacts' => [['name' => 'composer.lock', 'sha256' => str_repeat('0', 64)]],
        ]));

        $this->artisan('release:verify')
            ->expectsOutputToContain('UNGÜLTIG')
            ->assertExitCode(1);
    }

    // ── Helfer ───────────────────────────────────────────────────────────

    private function sandboxEnv(string $contents): string {
        $path = storage_path('framework/testing/env-' . bin2hex(random_bytes(5)));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        $this->app->instance(
            InstallationManager::class,
            new InstallationManager(new EnvWriter($path), $path . '-installed'),
        );

        return $path;
    }

    private function registerProbePlugin(string $id): void {
        $manager = new PluginManager;
        $manager->register(new ReportProbePlugin($id));
        $this->app->instance(PluginManager::class, $manager);
    }
}

/** Minimal-Plugin für plugin:list / plugin:doctor. */
final class ReportProbePlugin implements Plugin {
    use PluginDefaults;

    public function __construct(private readonly string $id) {}

    public function id(): string {
        return $this->id;
    }
    public function name(): string {
        return 'Report Probe';
    }
    public function version(): string {
        return '1.0.0';
    }
    public function description(): string {
        return '';
    }
    public function isEnabled(): bool {
        return true;
    }
    public function capabilities(): array {
        // Bewusst leer: plugin:doctor verlangt, dass jede angekündigte
        // Capability auch als Interface implementiert ist.
        return [];
    }
    public function adminPanel(): ?array {
        return null;
    }
    public function serviceProvider(): ?string {
        return null;
    }
    public function settingsSchema(): array {
        return [];
    }
    public function migrationsPath(): ?string {
        return null;
    }
    public function schemaVersion(): string {
        return '1.0.0';
    }
}
