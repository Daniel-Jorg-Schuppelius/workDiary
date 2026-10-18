<?php
/*
 * Created on   : Sat Oct 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ToolInstallerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Scripts;

use ConfigToolkit\ConfigTypes\ExecutableConfigType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Systemwerkzeuge der Toolkits im Deploy: Composer liefert das Install-
 * Werkzeug (Submodul installscript/ der Toolkits) nicht mit, der Installer
 * verlangt eine *executables.json im config/ des einbindenden Repos, und ohne
 * Root-Rechte bleibt dem Deploy nur die Prüfung. Jede dieser Fallen fiel auf
 * dem Server erst auf, als OCR und Textextraktion im Log als „nicht
 * verfügbar" standen (2026-10-17).
 */
class ToolInstallerTest extends TestCase {
    private string $root;

    private ?string $dir = null;

    protected function setUp(): void {
        parent::setUp();

        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Die Betriebs-Skripte sind Bash-Skripte.');
        }
        $this->root = dirname(__DIR__, 3);
    }

    protected function tearDown(): void {
        if ($this->dir !== null && is_dir($this->dir)) {
            $this->removeDir($this->dir);
        }

        parent::tearDown();
    }

    public function test_install_werkzeug_ist_als_submodul_eingebunden(): void {
        $modules = (string) file_get_contents($this->root . '/.gitmodules');

        $this->assertStringContainsString('path = installscript', $modules);
        $this->assertStringContainsString('DSchuppelius/install-dependencies', $modules);
        $this->assertFileExists(
            $this->root . '/installscript/install-dependencies.sh',
            'Submodul nicht geladen: git submodule update --init -- installscript'
        );
    }

    public function test_deploy_laedt_das_submodul_und_fuehrt_installer_oder_pruefung_aus(): void {
        $deploy = (string) file_get_contents($this->root . '/deploy.sh');

        $this->assertStringContainsString('git submodule update --init -- installscript', $deploy, 'reset --hard zieht Submodule nicht nach.');
        $this->assertStringContainsString('TOOLS_INSTALLER="installscript/install-dependencies.sh"', $deploy);
        $this->assertStringContainsString('bash "$TOOLS_INSTALLER"', $deploy);
        $this->assertStringContainsString('scripts/check-tools.php', $deploy, 'Ohne Root-Rechte muss der Deploy wenigstens melden, was fehlt.');
        $this->assertStringContainsString('find vendor -user root', $deploy, 'Root-eigene JARs unter vendor/ blockieren das nächste composer install.');
        $this->assertStringContainsString('runuser -u "$DEPLOY_OWNER" -- bash "$0" "$@"', $deploy, 'Als root gestartet laufen die App-Schritte als Eigentümer von storage/, root installiert danach nur die Werkzeuge.');
        $this->assertStringContainsString('DEPLOY_TOOLS_BY_ROOT=1', $deploy);

        $process = new Process(['bash', '-n', $this->root . '/deploy.sh']);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    public function test_app_konfiguration_lesen_installer_und_config_toolkit_gleich(): void {
        $data = json_decode((string) file_get_contents($this->root . '/config/executables.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue(
            ExecutableConfigType::matches($data),
            'Sonst liest der ConfigLoader die Datei als Struktur ohne Pfadauflösung — jeder Eintrag braucht path, kein linuxPath/windowsPath.'
        );
        $this->assertSame(['shellExecutables'], array_keys($data));

        foreach ($data['shellExecutables'] as $name => $entry) {
            $this->assertIsString($entry['path'], $name);
            $this->assertIsBool($entry['required'], $name);
            $this->assertNotEmpty($entry['package'], $name . ': ohne package installiert install-dependencies.sh nichts.');
            $this->assertNotEmpty($entry['description'], $name);
        }
    }

    public function test_pruefung_meldet_fehlende_erforderliche_werkzeuge_je_paket(): void {
        $this->fakeApp(present: ['wd-da']);

        $run = $this->check();

        $this->assertSame(1, $run->getExitCode(), $run->getErrorOutput());
        $output = $run->getOutput();
        $this->assertStringContainsString('erforderlich', $output);
        $this->assertStringContainsString('paket-fehlt', $output);
        $this->assertStringContainsString('wd-fehlt', $output);
        $this->assertStringContainsString('config/executables.json, acme-tool', $output, 'Beide Quellen desselben Programms werden genannt.');
        $this->assertStringNotContainsString('anderes-paket', $output, 'Ein später deklariertes Paket für ein bereits abgedecktes Programm zählt nicht (java → default-jre-headless vor default-jre).');
        $this->assertStringNotContainsString('paket-optional', $output, 'Optionale Werkzeuge erst mit --all.');
        $this->assertStringNotContainsString('paket-da', $output);
        $this->assertStringContainsString("Pakete: apt-get install -y paket-fehlt\n", $output);
    }

    public function test_pruefung_mit_all_nennt_optionale_werkzeuge_getrennt(): void {
        $this->fakeApp(present: ['wd-da', 'wd-fehlt']);

        $run = $this->check('--all');

        $this->assertSame(0, $run->getExitCode(), 'Fehlende optionale Werkzeuge sind kein Fehler.');
        $output = $run->getOutput();
        $this->assertStringContainsString('Fehlende optionale Werkzeuge', $output);
        $this->assertStringContainsString('paket-optional', $output);
        $this->assertStringNotContainsString('erforderlich', $output);
    }

    public function test_pruefung_ist_still_wenn_alles_vorhanden_ist(): void {
        $this->fakeApp(present: ['wd-da', 'wd-fehlt']);

        $run = $this->check();

        $this->assertSame(0, $run->getExitCode(), $run->getErrorOutput());
        $this->assertSame("Alle erforderlichen Systemwerkzeuge sind vorhanden.\n", $run->getOutput());
    }

    /**
     * Anwendungsverzeichnis mit App-Konfiguration, einem vendor-Paket und
     * einem bin/ mit den „vorhandenen" Programmen — PATH zeigt nur dorthin.
     *
     * @param list<string> $present
     */
    private function fakeApp(array $present): void {
        $this->dir = sys_get_temp_dir() . '/wd-tools-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/bin', 0700, true);
        mkdir($this->dir . '/config', 0700, true);
        mkdir($this->dir . '/vendor/acme/acme-tool/config', 0700, true);

        foreach ($present as $program) {
            file_put_contents($this->dir . '/bin/' . $program, "#!/bin/sh\nexit 0\n");
            chmod($this->dir . '/bin/' . $program, 0700);
        }

        file_put_contents($this->dir . '/config/executables.json', json_encode([
            'shellExecutables' => [
                'da' => ['path' => 'wd-da', 'required' => true, 'package' => 'paket-da', 'description' => 'vorhanden'],
                'fehlt' => ['path' => 'wd-fehlt', 'required' => true, 'package' => 'paket-fehlt', 'description' => 'fehlt'],
                'optional' => ['path' => 'wd-optional', 'required' => false, 'package' => 'paket-optional', 'description' => 'optional'],
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->dir . '/vendor/acme/acme-tool/config/tool_executables.json', json_encode([
            'shellExecutables' => [
                'fehlt-auch' => ['path' => 'wd-fehlt', 'required' => true, 'package' => 'anderes-paket', 'description' => 'dasselbe Programm, anderes Paket'],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    private function check(string ...$args): Process {
        $process = new Process(
            [PHP_BINARY, $this->root . '/scripts/check-tools.php', '--app-dir=' . $this->dir, ...$args],
            null,
            ['PATH' => $this->dir . '/bin'],
        );
        $process->run();

        return $process;
    }

    private function removeDir(string $dir): void {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
