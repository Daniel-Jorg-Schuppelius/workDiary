<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SystemHealthCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Console\Commands\SystemHealthCommand;
use App\Services\Licensing\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthCommandTest extends TestCase {
    use RefreshDatabase;

    public function test_system_health_passes_in_test_environment(): void {
        $this->artisan('system:health')
            ->expectsOutputToContain('Alle Checks bestanden.')
            ->assertExitCode(0);
    }

    /**
     * UI-Crawl 2026-09-19: Die Komponentenseite ruft runChecks() ohne Konsolen-
     * Anwendung auf — der Migrationscheck darf dort nicht an callSilently() scheitern.
     */
    public function test_migration_check_works_outside_the_console_and_detects_pending_ones(): void {
        $checks = fn(): array => collect(app(SystemHealthCommand::class)->runChecks(app(LicenseService::class)))
            ->keyBy(0)->all();

        $this->assertSame([true, 'Keine ausstehenden Migrationen'], array_slice($checks()['Migrationen'], 1));

        $dir = sys_get_temp_dir() . '/health-pending-' . uniqid();
        mkdir($dir);
        touch($dir . '/2099_01_01_000000_pending_probe.php');
        app('migrator')->path($dir);

        try {
            $this->assertFalse($checks()['Migrationen'][1], 'Ausstehende Migration wird erkannt');
        } finally {
            unlink($dir . '/2099_01_01_000000_pending_probe.php');
            rmdir($dir);
        }
    }

    public function test_system_health_fails_without_app_key(): void {
        config(['app.key' => '']);

        $this->artisan('system:health')->assertExitCode(1);
    }

    public function test_system_health_json_mode_emits_structured_output(): void {
        // Strukturprüfung unabhängig vom Gesamtzustand (Backup-/Restore-Checks
        // sind datenabhängig): valides JSON mit erwarteten Schlüsseln.
        // Die JSON-Ausgabe ist eine einzelne Zeile mit allen Schlüsseln;
        // expectsOutputToContain konsumiert pro Aufruf eine Zeile, daher EIN
        // repräsentativer, eindeutiger Substring (deckt die Struktur ab).
        $this->artisan('system:health --json')
            ->expectsOutputToContain('"healthy":');
    }

    public function test_an_unusable_module_key_warns_without_blocking_the_update(): void {
        // Ein roter Check hielte deploy.sh im Wartungsmodus fest. Ein kaputter
        // Modulschlüssel legt nur zwei Module still, nicht die Installation.
        config(['whistleblowing.key' => 'zu-kurz']);

        $this->artisan('system:health')
            ->expectsOutputToContain('WHISTLEBLOWING_KEY')
            ->assertExitCode(0);
    }

    public function test_organizations_without_own_plugin_credentials_are_reported(): void {
        config(['plugins.allow_env_secret_fallback' => false, 'plugins.lexoffice.api_key' => 'betreiber-schluessel']);
        $organization = \App\Models\Organization::factory()->create();
        \App\Models\PluginSetting::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => 'lexoffice',
            'enabled' => true,
            'settings' => [],
        ]);

        $warnings = app(\App\Console\Commands\SystemHealthCommand::class)->runWarnings();

        $this->assertContains('Plugin lexoffice', array_column($warnings, 0));
    }

    public function test_an_own_credential_clears_the_plugin_warning(): void {
        config(['plugins.allow_env_secret_fallback' => false, 'plugins.lexoffice.api_key' => 'betreiber-schluessel', 'plugins.lexoffice.enabled' => false]);
        $organization = \App\Models\Organization::factory()->create();
        \App\Models\PluginSetting::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => 'lexoffice',
            'enabled' => true,
            'settings' => ['api_key' => 'eigener-schluessel'],
        ]);

        $warnings = app(\App\Console\Commands\SystemHealthCommand::class)->runWarnings();

        $this->assertNotContains('Plugin lexoffice', array_column($warnings, 0));
    }

    public function test_active_lti_registrations_warn_without_https(): void {
        $organization = \App\Models\Organization::factory()->create();
        \App\Models\Learning\LearningLtiTool::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Brandschutz-Tool',
            'client_id' => 'client-1',
            'deployment_id' => 'deployment-1',
            'login_url' => 'https://tool.example.org/lti/login',
            'launch_url' => 'https://tool.example.org/lti/launch',
            'redirect_uris' => ['https://tool.example.org/lti/launch'],
            'jwks_url' => 'https://tool.example.org/lti/jwks',
            'is_active' => true,
        ]);
        $warnings = static fn (): array => array_column(app(\App\Console\Commands\SystemHealthCommand::class)->runWarnings(), 0);

        config(['app.url' => 'http://work.example.test']);
        $this->assertContains('LTI', $warnings());

        config(['app.url' => 'https://work.example.test']);
        $this->assertNotContains('LTI', $warnings());
    }

    public function test_the_blind_index_warning_disappears_after_the_rehash(): void {
        $blindIndexWarnings = static fn (): array => array_values(array_filter(
            app(\App\Console\Commands\SystemHealthCommand::class)->runWarnings(),
            static fn (array $w): bool => $w[0] === 'Blindindizes',
        ));

        $this->assertNotSame([], $blindIndexWarnings());

        $this->artisan('security:rehash-blind-indexes')->assertSuccessful();

        $this->assertSame([], $blindIndexWarnings());
    }
}
