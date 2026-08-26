<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResetInstallCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Services\Install\{EnvWriter, InstallationManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File, Schema, Storage};
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7 / MVP-725: `app:reset-install` ist destruktiv
 * (entfernt den Install-Marker, optional migrate:fresh, leert die Caches).
 *
 * Getestet wird jetzt beides:
 *  - der **Guard**: ohne `--force` bricht die verneinte Sicherheitsabfrage ab,
 *    ohne den Marker anzufassen;
 *  - der **Destruktivzweig** — aber gegen Attrappen statt gegen die Umgebung
 *    des Testlaufs: Install-Marker und .env liegen in einem temporären
 *    Verzeichnis (Container-Binding, wie im Install-Wizard-Test), `migrate:fresh`
 *    läuft gegen eine SQLite-Scratch-Datenbank, und die Cache-Pfade von
 *    `optimize:clear` zeigen auf dasselbe temporäre Verzeichnis. Ohne diese
 *    Umlenkung würde der Lauf die Datenbank des Testprozesses leeren und
 *    bootstrap/cache/* unter den parallel laufenden Workern wegräumen.
 */
class ResetInstallCommandTest extends TestCase {
    use RefreshDatabase;

    /** Cache-Pfade, die `optimize:clear`/`clear-compiled` löschen würde. */
    private const CACHE_PATH_ENV = [
        'APP_SERVICES_CACHE' => 'services.php',
        'APP_PACKAGES_CACHE' => 'packages.php',
        'APP_CONFIG_CACHE' => 'config.php',
        'APP_ROUTES_CACHE' => 'routes.php',
        'APP_EVENTS_CACHE' => 'events.php',
    ];

    private string $sandbox;

    private ?string $originalConnection = null;

    /** @var array<string, string|false> */
    private array $originalCacheEnv = [];

    protected function setUp(): void {
        parent::setUp();

        $this->sandbox = storage_path('framework/testing/reset-install-' . bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->sandbox);
    }

    protected function tearDown(): void {
        // Erst die Default-Verbindung zurückdrehen — RefreshDatabase rollt am
        // Ende die Transaktion der DEFAULT-Verbindung zurück und würde sonst
        // die Scratch-DB erwischen.
        if ($this->originalConnection !== null) {
            config(['database.default' => $this->originalConnection]);
            \Illuminate\Support\Facades\DB::setDefaultConnection($this->originalConnection);
            $this->originalConnection = null;
        }

        foreach ($this->originalCacheEnv as $key => $value) {
            if ($value === false) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $_SERVER[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
        $this->originalCacheEnv = [];

        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    /** Marker + .env des Kommandos auf das Sandbox-Verzeichnis umbiegen. */
    private function sandboxInstaller(): InstallationManager {
        $envPath = $this->sandbox . '/.env';
        $lockPath = $this->sandbox . '/installed';
        File::put($envPath, "APP_NAME=WorkDiary\n");
        File::put($lockPath, '{"installed_at":"2026-01-01T00:00:00+00:00"}');

        $installer = new InstallationManager(new EnvWriter($envPath), $lockPath);
        $this->app->instance(InstallationManager::class, $installer);

        return $installer;
    }

    public function test_declining_the_confirmation_aborts_without_touching_the_marker(): void {
        $installer = $this->sandboxInstaller();

        $this->artisan('app:reset-install')
            ->expectsConfirmation('Installationsstatus zurücksetzen und Wizard erneut freischalten?', 'no')
            ->expectsOutputToContain('Abgebrochen.')
            ->assertExitCode(0);

        $this->assertFileExists($installer->lockPath(), 'Abbruch darf den Install-Marker nicht entfernen.');
    }

    public function test_force_removes_the_marker_and_clears_caches(): void {
        $installer = $this->sandboxInstaller();
        $this->redirectFrameworkCaches();
        Storage::fake('local');

        $this->artisan('app:reset-install --force')
            ->expectsOutputToContain('Installations-Marker entfernt')
            ->expectsOutputToContain('Leere Caches')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($installer->lockPath(), '--force entfernt den Marker ohne Rückfrage.');
    }

    public function test_second_run_reports_a_missing_marker_instead_of_failing(): void {
        $installer = $this->sandboxInstaller();
        $this->redirectFrameworkCaches();
        @unlink($installer->lockPath());

        $this->artisan('app:reset-install --force')
            ->expectsOutputToContain('Kein Installations-Marker vorhanden.')
            ->assertExitCode(0);
    }

    public function test_fresh_declined_keeps_the_database(): void {
        $this->sandboxInstaller();
        $this->redirectFrameworkCaches();

        // --fresh ohne --force: die zweite Rückfrage verneinen → keine Migration.
        $this->artisan('app:reset-install --fresh')
            ->expectsConfirmation('Installationsstatus zurücksetzen und Wizard erneut freischalten?', 'yes')
            ->expectsConfirmation('ALLE Tabellen verwerfen und neu migrieren (migrate:fresh)?', 'no')
            ->expectsOutputToContain('Datenbank NICHT geleert.')
            ->assertExitCode(0);

        // Der Grundbestand des Testlaufs steht noch.
        $this->assertTrue(Schema::hasTable('users'));
    }

    /**
     * Der teuerste Test dieser Datei (~1 min): `migrate:fresh` läuft gegen die
     * Scratch-Verbindung, deren Name keinen Schema-Dump hat — Laravel spielt
     * daher alle Migrationen einzeln ein. Genau das ist der Nachweis: der
     * Destruktivzweig baut wirklich ein Schema auf, und zwar dort.
     */
    public function test_fresh_force_migrates_the_scratch_database(): void {
        $installer = $this->sandboxInstaller();
        $this->redirectFrameworkCaches();
        Storage::fake('local');
        $this->useScratchDatabase();

        // Beweisführung: die Scratch-DB ist vor dem Lauf leer.
        $this->assertFalse(Schema::connection('reset_install_scratch')->hasTable('users'));

        $this->artisan('app:reset-install --fresh --force')
            ->expectsOutputToContain('Datenbank neu migriert.')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($installer->lockPath());
        $this->assertTrue(
            Schema::connection('reset_install_scratch')->hasTable('users'),
            'migrate:fresh muss das Schema in der Scratch-DB aufgebaut haben.',
        );
    }

    /**
     * `optimize:clear` löscht die kompilierten Framework-Caches. In der
     * Testsuite (parallele Worker) darf das nicht bootstrap/cache/* treffen —
     * die Pfade kommen aus ENV und werden hier in die Sandbox umgehängt.
     */
    private function redirectFrameworkCaches(): void {
        foreach (self::CACHE_PATH_ENV as $key => $file) {
            $this->originalCacheEnv[$key] = $_SERVER[$key] ?? false;
            $target = $this->sandbox . '/' . $file;
            $_ENV[$key] = $_SERVER[$key] = $target;
            putenv($key . '=' . $target);
        }
        config(['view.compiled' => $this->sandbox]);

        if ($this->app->getCachedServicesPath() !== $this->sandbox . '/services.php') {
            $this->markTestSkipped('Cache-Pfade lassen sich in dieser Umgebung nicht umlenken — Lauf wäre destruktiv.');
        }
    }

    /** Leere SQLite-Datei als Default-Verbindung — Ziel des migrate:fresh. */
    private function useScratchDatabase(): void {
        $path = $this->sandbox . '/scratch.sqlite';
        File::put($path, '');

        $this->originalConnection = (string) config('database.default');
        config([
            'database.connections.reset_install_scratch' => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.default' => 'reset_install_scratch',
        ]);
        \Illuminate\Support\Facades\DB::setDefaultConnection('reset_install_scratch');
    }
}
