<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InstallCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use App\Console\Commands\InstallCommand;
use App\Models\Platform\User;
use App\Services\Install\InstallationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * `app:install` läuft gegen eine Attrappe des InstallationManager — der echte
 * schreibt APP_KEY und Datenbankzugang in die .env und biegt die
 * Laufzeit-Verbindung um. Nur `runMigrations()` bleibt echt, weil sein
 * verschachtelter `Artisan::call('migrate')` den Fehler auslöste: Laravel
 * bindet die statische Prompt-Ausgabe beim Start jedes Commands neu; nach dem
 * inneren Aufruf zeigte sie auf dessen Puffer, die Frage nach dem Namen der
 * Organisation blieb unsichtbar und der Installer wartete endlos auf Eingabe.
 *
 * Bewusst nicht über `$this->artisan()`: dessen OutputStyle-Attrappe wird an
 * alle Commands ausgereicht, auch an den inneren, sodass der Fehler dort nie
 * sichtbar würde. Stattdessen läuft `Command::run()` mit echter Ausgabe und
 * einem Eingabestrom voller Antworten. Ohne die Rückbindung liest der Prompt
 * nach den Migrationen von STDIN: unter CI (`/dev/null`) bricht der Command
 * mit „Aborted." ab, im Terminal wartet er auf die Tastatur.
 */
class InstallCommandTest extends TestCase {
    use RefreshDatabase;

    public function test_prompts_after_the_migrations_still_reach_the_console(): void {
        $admin = new User();
        $admin->email = 'admin@example.test';

        $this->partialMock(InstallationManager::class, function (MockInterface $mock) use ($admin): void {
            $mock->shouldReceive('isInstalled')->once()->andReturnFalse();
            $mock->shouldReceive('requirements')->with('sqlite')->andReturn([]);
            $mock->shouldReceive('configureApp')->once()->with([
                'app_name' => 'WorkDiary Test',
                'app_url' => 'https://workdiary.test',
                'app_env' => 'local',
                'locale' => 'de',
                'timezone' => 'Europe/Berlin',
            ]);
            $mock->shouldReceive('testConnection')->once()->andReturnTrue();
            $mock->shouldReceive('configureDatabase')->once()->with([
                'driver' => 'sqlite',
                'database' => '/tmp/install-test.sqlite',
            ]);
            // runMigrations() bleibt echt: `migrate --force` gegen die bereits
            // migrierte Test-DB ist ein Leerlauf, startet aber den inneren Command.
            $mock->shouldReceive('seedRolesAndPermissions')->once();
            $mock->shouldReceive('createOrganizationAndAdmin')->once()->with([
                'org_name' => 'Acme GmbH',
                'name' => 'Ada Admin',
                'email' => 'admin@example.test',
                'password' => 'geheim-123',
            ])->andReturn($admin);
            $mock->shouldReceive('markInstalled')->once();
            $mock->shouldReceive('clearCaches')->once();
        });

        $console = $this->runInstaller([
            'sqlite',                   // Datenbank-Treiber
            'WorkDiary Test',           // Anwendungsname
            'https://workdiary.test',   // Anwendungs-URL
            'local',                    // Umgebung
            'de',                       // Sprache
            'Europe/Berlin',            // Zeitzone
            '/tmp/install-test.sqlite', // SQLite-Dateipfad
            'Acme GmbH',                // Name der Organisation — erster Prompt nach den Migrationen
            'Ada Admin',                // Name des Administrators
            'admin@example.test',       // E-Mail des Administrators
            'geheim-123',               // Passwort
            'no',                       // E-Mail/SMTP jetzt konfigurieren?
            'no',                       // Integrationen jetzt konfigurieren?
        ]);

        $this->assertSame(0, $console['exit'], $console['output']);
        $this->assertStringContainsString('Datenbank konfiguriert & migriert', $console['output']);
        $this->assertStringContainsString('Name der Organisation', $console['output']);
        $this->assertStringContainsString('Administrator angelegt: admin@example.test', $console['output']);
        $this->assertStringContainsString('Installation abgeschlossen', $console['output']);
    }

    /**
     * @param  list<string>  $answers
     * @return array{exit: int, output: string}
     */
    private function runInstaller(array $answers): array {
        // Der versteckte Passwort-Prompt soll kein stty auf dem Testterminal ausführen.
        QuestionHelper::disableStty();

        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, implode(PHP_EOL, $answers) . PHP_EOL);
        rewind($stream);

        $input = new ArrayInput([]);
        $input->setStream($stream);
        $output = new BufferedOutput();

        $command = $this->app->make(InstallCommand::class);
        $command->setLaravel($this->app);

        $exit = $command->run($input, $output);

        return ['exit' => $exit, 'output' => $output->fetch()];
    }
}
