<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdminAndAccessCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use App\Http\Controllers\Api\LocationController;
use App\Models\Location\LocationDeviceToken;
use App\Models\User;
use App\Services\Hr\PersonnelFilePermissions;
use App\Services\Install\InstallationManager;
use App\Services\Whistleblowing\WhistleblowingPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7 (MVP-725): Betriebs-Kommandos rund um Zugang,
 * Rollen-Backfill und den GoBD-Audit-Export.
 *
 * `app:install` wird bewusst nur auf seinem Guard geprüft — der interaktive
 * Durchlauf schreibt APP_KEY und Datenbankzugang in die echte .env.
 */
class AdminAndAccessCommandsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ── app:admin ────────────────────────────────────────────────────────

    /**
     * ACHTUNG (Grund für die Doppel statt echter Anlage): der
     * {@see \App\Services\Install\OrganizationProvisioner} ruft
     * `applyConfiguredDatabaseToRuntime()` und biegt die Laufzeit-Verbindung
     * auf die in der **.env** konfigurierte Datenbank um — im Test also auf die
     * Entwicklungs-DB, nicht auf die Test-DB. Ein Test, der `app:admin` in
     * seinem Anlage-/Reset-Zweig wirklich ausführt, schreibt an der
     * RefreshDatabase-Transaktion vorbei in fremde Daten. Geprüft wird deshalb
     * genau die Naht Kommando → Installer.
     */
    public function test_admin_command_hands_the_options_to_the_installer(): void {
        $user = $this->orgAdmin(['email' => 'neu@example.test']);

        $installer = Mockery::mock(InstallationManager::class);
        $installer->shouldReceive('createOrganizationAndAdmin')
            ->once()
            ->with([
                'org_name' => 'Frisch GmbH',
                'name' => 'Neue Chefin',
                'email' => 'chefin@example.test',
                'password' => 'sehr-geheim-123',
            ], true)
            ->andReturn($user);
        $this->app->instance(InstallationManager::class, $installer);

        $this->artisan('app:admin', [
            '--email' => 'chefin@example.test',
            '--name' => 'Neue Chefin',
            '--org' => 'Frisch GmbH',
            '--password' => 'sehr-geheim-123',
            '--platform' => true,
        ])
            // Ein Teilstring je Ausgabezeile — zwei würden einander in der
            // Mockery-Erwartung verdecken.
            ->expectsOutputToContain('Administrator angelegt: neu@example.test (Plattform-Betreiber)')
            ->assertExitCode(0);
    }

    public function test_admin_command_resets_an_existing_user_via_the_installer(): void {
        $user = $this->orgAdmin(['email' => 'chef@example.test']);

        $installer = Mockery::mock(InstallationManager::class);
        $installer->shouldReceive('resetAdminPassword')
            ->once()
            ->with('chef@example.test', 'neues-passwort-456')
            ->andReturn($user);
        $this->app->instance(InstallationManager::class, $installer);

        // Ohne --reset: der vorhandene Benutzer schaltet den Reset-Zweig selbst frei.
        $this->artisan('app:admin', [
            '--email' => 'chef@example.test',
            '--password' => 'neues-passwort-456',
        ])
            ->expectsOutputToContain('Passwort aktualisiert')
            ->assertExitCode(0);
    }

    public function test_admin_command_reports_a_failing_installer(): void {
        $this->orgAdmin(['email' => 'chef@example.test']);

        $installer = Mockery::mock(InstallationManager::class);
        $installer->shouldReceive('resetAdminPassword')
            ->once()
            ->andThrow(new RuntimeException('Datenbank nicht erreichbar'));
        $this->app->instance(InstallationManager::class, $installer);

        $this->artisan('app:admin', [
            '--email' => 'chef@example.test',
            '--password' => 'neues-passwort-456',
        ])
            ->expectsOutputToContain('Vorgang fehlgeschlagen: Datenbank nicht erreichbar')
            ->assertExitCode(1);
    }

    public function test_admin_command_fails_when_resetting_an_unknown_user(): void {
        $this->artisan('app:admin', [
            '--email' => 'niemand@example.test',
            '--password' => 'egal-egal-789',
            '--reset' => true,
        ])
            ->expectsOutputToContain('Kein Benutzer mit E-Mail niemand@example.test gefunden.')
            ->assertExitCode(1);
    }

    // ── app:install ──────────────────────────────────────────────────────

    public function test_install_command_refuses_to_overwrite_an_installation(): void {
        // Die Testsuite läuft mit APP_INSTALLED=true — genau der Guard.
        $this->artisan('app:install')
            ->expectsOutputToContain('bereits installiert')
            ->assertExitCode(1);
    }

    // ── location:device-token ────────────────────────────────────────────

    public function test_device_token_command_issues_a_token_and_sets_the_opt_in(): void {
        $user = $this->orgUser(['email' => 'monteur@example.test']);
        $this->assertFalse((bool) $user->getPreference(LocationController::OPT_IN_PREFERENCE, false));

        $this->artisan('location:device-token', [
            'user' => 'monteur@example.test',
            '--label' => 'Diensthandy',
        ])
            ->expectsOutputToContain('/api/location/ingest/')
            ->expectsOutputToContain('Opt-in zur Standorterfassung aktiviert.')
            ->assertExitCode(0);

        $this->assertSame(1, LocationDeviceToken::query()->where('user_id', $user->id)->count());
        $this->assertTrue((bool) $user->fresh()?->getPreference(LocationController::OPT_IN_PREFERENCE, false));
    }

    public function test_device_token_command_fails_for_an_unknown_user(): void {
        $this->artisan('location:device-token', ['user' => 'gibt-es-nicht@example.test'])
            ->expectsOutputToContain('Nutzer nicht gefunden')
            ->assertExitCode(1);
    }

    // ── personalakte:seed-roles / whistleblowing:seed-roles ──────────────

    public function test_personnel_file_roles_are_backfilled(): void {
        $this->artisan('personalakte:seed-roles', ['organization' => (string) $this->organization->id])
            ->expectsOutputToContain('1 Organisation(en) verarbeitet')
            ->assertExitCode(0);

        $this->assertTrue($this->roleExists(PersonnelFilePermissions::ROLE_PERSONALAKTE));
    }

    public function test_whistleblowing_roles_are_backfilled_idempotently(): void {
        $this->artisan('whistleblowing:seed-roles')->assertExitCode(0);
        $this->artisan('whistleblowing:seed-roles')->assertExitCode(0);

        $this->assertSame(
            1,
            Role::query()
                ->where('name', WhistleblowingPermissions::ROLE_MELDESTELLE)
                ->where('team_id', $this->organization->id)
                ->count(),
            'Der Backfill muss idempotent sein — sonst entstehen doppelte Rollen.',
        );
    }

    public function test_role_backfill_fails_for_an_unknown_organization(): void {
        $this->artisan('personalakte:seed-roles', ['organization' => '999999'])
            ->expectsOutputToContain('Organisation #999999 nicht gefunden.')
            ->assertExitCode(1);
    }

    // ── audit:export ─────────────────────────────────────────────────────

    public function test_audit_export_writes_a_zip_with_manifest(): void {
        Storage::fake('local');

        $this->artisan('audit:export', ['--chain' => 'audit_logs'])
            ->expectsOutputToContain('Audit-Export erstellt')
            ->assertExitCode(0);

        $files = Storage::disk('local')->files('audit-exports');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.zip', $files[0]);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($files[0])) === true);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('audit_logs', $manifest['chains']);
        $this->assertNotEmpty($manifest['note']);
    }

    public function test_audit_export_rejects_an_unknown_chain(): void {
        $this->artisan('audit:export', ['--chain' => 'gibt-es-nicht'])
            ->expectsOutputToContain('Unbekannte Kette: gibt-es-nicht')
            ->assertExitCode(2);
    }

    // ── accounting:seed-load ─────────────────────────────────────────────

    public function test_accounting_load_seeder_is_blocked_in_production(): void {
        $this->app->instance('env', 'production');

        $this->artisan('accounting:seed-load')
            ->expectsOutputToContain('In der Produktivumgebung gesperrt')
            ->assertExitCode(1);
    }

    public function test_accounting_load_seeder_needs_a_user_in_the_organization(): void {
        // Organisation ohne Benutzer: der Messdatensatz braucht eine
        // handelnde Person für created_by/posted_by.
        User::query()->where('organization_id', $this->organization->id)->delete();

        $this->artisan('accounting:seed-load', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('Keine Benutzerin/kein Benutzer in der Organisation.')
            ->assertExitCode(1);
    }

    public function test_accounting_load_seeder_writes_measurement_entries(): void {
        $this->orgAdmin();

        $this->artisan('accounting:seed-load', [
            '--organization' => (string) $this->organization->id,
            '--years' => '1',
            '--entries' => '4',
            '--open-items' => '2',
        ])
            ->expectsOutputToContain('Fertig: 4 Buchungen')
            ->assertExitCode(0);

        $this->assertSame(4, DB::table('accounting_entries')->where('organization_id', $this->organization->id)->count());
    }

    private function roleExists(string $name): bool {
        return Role::query()
            ->where('name', $name)
            ->where('team_id', $this->organization->id)
            ->exists();
    }
}
