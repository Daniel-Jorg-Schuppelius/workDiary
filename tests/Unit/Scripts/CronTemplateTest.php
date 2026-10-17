<?php
/*
 * Created on   : Fri Oct 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CronTemplateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Scripts;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * deploy/cron.d/workdiary.template + scripts/install-system.sh (MVP-1205):
 * Backup und Restore-Test laufen als root aus root-eigenen Kopien. Ein
 * vergessener Platzhalter oder ein Filter, der eine Zeile des abgewählten
 * Blocks stehen lässt, fällt sonst erst auf dem Server auf — als Cron-Zeile,
 * die nie startet, oder als Restore-Test ohne Backup.
 */
class CronTemplateTest extends TestCase {
    private string $root;

    protected function setUp(): void {
        parent::setUp();

        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Die Betriebs-Skripte sind Bash-Skripte.');
        }
        $this->root = dirname(__DIR__, 3);
    }

    public function test_jeder_platzhalter_der_vorlage_wird_vom_installer_ersetzt(): void {
        preg_match_all('/__[A-Z_]+__/', (string) file_get_contents($this->root . '/deploy/cron.d/workdiary.template'), $matches);
        $installer = (string) file_get_contents($this->root . '/scripts/install-system.sh');

        $missing = [];
        foreach (array_unique($matches[0]) as $placeholder) {
            if (!str_contains($installer, 's|' . $placeholder . '|')) {
                $missing[] = $placeholder;
            }
        }

        $this->assertSame([], $missing, 'Platzhalter ohne sed-Ersetzung in render() des Installers.');
    }

    public function test_betriebsskripte_sind_syntaktisch_gueltig(): void {
        foreach (['scripts/backup.sh', 'scripts/restore-test.sh', 'scripts/install-system.sh'] as $script) {
            $process = new Process(['bash', '-n', $this->root . '/' . $script]);
            $process->run();

            $this->assertSame(0, $process->getExitCode(), $script . ': ' . $process->getErrorOutput());
        }
    }

    public function test_installer_rendert_backup_und_restore_test_ohne_platzhalter(): void {
        $output = $this->dryRun(['--restore-test-day', '3', '--restore-test-time', '05:15']);

        $this->assertDoesNotMatchRegularExpression('/__[A-Z_]+__/', $output);
        $this->assertStringContainsString(' root BACKUP_CONF=/etc/workdiary-backup.conf /usr/local/sbin/workdiary-backup.sh', $output);
        $this->assertStringContainsString(
            '15 5 * * 3 root BACKUP_CONF=/etc/workdiary-backup.conf PHP_BIN=' . PHP_BINARY
                . ' /usr/local/sbin/workdiary-restore-test.sh --prune-stale >> /var/log/workdiary-restore-test.log 2>&1',
            $output,
        );
        $this->assertStringContainsString('restore-test.sh -> ', $output, 'root-eigene Kopie des Restore-Tests');
    }

    public function test_ohne_restore_test_bleibt_keine_zeile_des_blocks_stehen(): void {
        $output = $this->dryRun(['--no-restore-test']);

        // Die Backup-Konfiguration nennt restore-test.sh als Leser — geprüft wird der Cron-Abschnitt.
        $this->assertStringContainsString('workdiary-backup.sh >> /var/log/workdiary-backup.log', $output);
        $this->assertStringNotContainsString('restore-test', $this->cronBlock($output));
        $this->assertStringNotContainsString('Restore-Test', $this->cronBlock($output));
        $this->assertStringNotContainsString('restore-test.sh -> ', $output, 'keine root-eigene Kopie ohne Restore-Test');
    }

    public function test_ohne_backup_entfaellt_auch_der_restore_test(): void {
        $output = $this->dryRun(['--no-backup']);

        $this->assertStringContainsString('schedule:run', $output);
        $this->assertStringNotContainsString('backup.sh', $this->cronBlock($output));
        $this->assertStringNotContainsString('restore-test', $this->cronBlock($output));
        $this->assertStringNotContainsString('root-eigene', $this->cronBlock($output));
    }

    public function test_ungueltiger_wochentag_wird_abgewiesen(): void {
        $process = $this->installer(['--restore-test-day', '7']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('--restore-test-day erwartet 0-6', $process->getErrorOutput());
    }

    /** @param list<string> $args */
    private function dryRun(array $args): string {
        $process = $this->installer([...$args, '--dry-run']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());

        return $process->getOutput();
    }

    /** @param list<string> $args */
    private function installer(array $args): Process {
        // DESTDIR hält alles unter einem Präfix; PHP_BIN = laufendes PHP, damit
        // die Erkennung nicht vom PATH der Testmaschine abhängt.
        $process = new Process(
            ['bash', $this->root . '/scripts/install-system.sh', ...$args],
            $this->root,
            ['DESTDIR' => sys_get_temp_dir() . '/wd-cron-' . bin2hex(random_bytes(4)), 'PHP_BIN' => PHP_BINARY, 'APP_DIR' => $this->root],
        );
        $process->run();

        return $process;
    }

    /** Nur der gerenderte Cron-Abschnitt der Dry-Run-Ausgabe (bis zur nächsten „würde schreiben“-Marke). */
    private function cronBlock(string $output): string {
        $start = strpos($output, '/etc/cron.d/');
        $this->assertNotFalse($start, 'Dry-Run zeigt keine Cron-Datei.');
        $rest = substr($output, $start);
        $end = strpos($rest, '--- würde', 10);

        return $end === false ? $rest : substr($rest, 0, $end);
    }
}
