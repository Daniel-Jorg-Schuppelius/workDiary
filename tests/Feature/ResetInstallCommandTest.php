<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResetInstallCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Install\InstallationManager;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7: app:reset-install ist destruktiv (entfernt den
 * Install-Marker, optional migrate:fresh) — getestet wird deshalb NUR der
 * Guard-Pfad: ohne --force muss die verneinte Sicherheitsabfrage abbrechen,
 * ohne den Marker anzufassen. Der destruktive Zweig bleibt bewusst ungetestet
 * (würde Test-Storage/-DB zerlegen).
 */
class ResetInstallCommandTest extends TestCase {
    public function test_declining_the_confirmation_aborts_without_touching_the_marker(): void {
        $installer = app(InstallationManager::class);
        $lockPath = $installer->lockPath();

        // Marker sicherstellen, damit „unangetastet" beweisbar ist — aber nur
        // aufräumen, wenn wir ihn selbst angelegt haben (Dev-Marker schonen).
        $createdByTest = false;
        if (! is_file($lockPath)) {
            @mkdir(dirname($lockPath), 0755, true);
            file_put_contents($lockPath, 'test-marker');
            $createdByTest = true;
        }

        try {
            $this->artisan('app:reset-install')
                ->expectsConfirmation('Installationsstatus zurücksetzen und Wizard erneut freischalten?', 'no')
                ->expectsOutputToContain('Abgebrochen.')
                ->assertExitCode(0);

            $this->assertFileExists($lockPath, 'Abbruch darf den Install-Marker nicht entfernen.');
        } finally {
            if ($createdByTest) {
                @unlink($lockPath);
            }
        }
    }
}
