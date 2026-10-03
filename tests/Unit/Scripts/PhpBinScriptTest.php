<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhpBinScriptTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Scripts;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * scripts/lib/php-bin.sh: Auf Servern mit mehreren PHP-Versionen ist `php`
 * oft älter als die Version des Webs. Die Shell-Skripte suchen deshalb das
 * Binary passend zu `require.php` der composer.json — ein nackter
 * `php artisan`-Aufruf liefe dort in Composers Plattform-Check und bräche
 * den Deploy mitten im Wartungsmodus ab.
 */
class PhpBinScriptTest extends TestCase {
    /** Container bringen genau ein PHP mit; backup.sh ruft kein PHP auf. */
    private const SCRIPTS = ['deploy.sh', 'scripts/*.sh', 'scripts/lib/*.sh', 'tests/e2e/*.sh'];

    private string $dir;

    protected function setUp(): void {
        parent::setUp();

        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Die Betriebs-Skripte sind Bash-Skripte.');
        }

        $this->dir = sys_get_temp_dir() . '/wd-php-bin-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/bin', 0700, true);
        mkdir($this->dir . '/app', 0700);
        // PHP 7.9 gab es nie: weder das echte `php` noch /usr/bin/php7.9 mischen sich ein.
        file_put_contents($this->dir . '/app/composer.json', "{\n    \"require\": {\n        \"php\": \"^7.9\",\n        \"php-http/discovery\": \"^1.0\"\n    }\n}\n");
    }

    protected function tearDown(): void {
        if (isset($this->dir) && is_dir($this->dir)) {
            foreach (['/bin', '/app'] as $sub) {
                array_map(unlink(...), glob($this->dir . $sub . '/*') ?: []);
                rmdir($this->dir . $sub);
            }
            rmdir($this->dir);
        }

        parent::tearDown();
    }

    public function test_versioniertes_binary_gewinnt_vor_zu_altem_php(): void {
        $this->fakePhp('php', '7.4');
        $this->fakePhp('php7.9', '7.9');

        $run = $this->bash('resolve_php_bin "$APP"');

        $this->assertSame(0, $run->getExitCode(), $run->getErrorOutput());
        $this->assertSame($this->dir . '/bin/php7.9', $run->getOutput());
    }

    public function test_php_ohne_versionssuffix_genuegt_wenn_es_passt(): void {
        $this->fakePhp('php', '7.9');

        $this->assertSame($this->dir . '/bin/php', $this->bash('resolve_php_bin "$APP"')->getOutput());
    }

    public function test_passende_vorgabe_hat_vorrang(): void {
        $this->fakePhp('php7.9', '7.9');
        $this->fakePhp('eigenes-php', '7.9');

        $this->assertSame($this->dir . '/bin/eigenes-php', $this->bash('resolve_php_bin "$APP" eigenes-php')->getOutput());
    }

    public function test_unpassende_vorgabe_weicht_auf_die_suche_aus(): void {
        // Der Fall nach einer PHP-Anhebung: die Crontab trägt noch PHP_BIN=php7.4.
        $this->fakePhp('php7.4', '7.4');
        $this->fakePhp('php7.9', '7.9');

        $run = $this->bash('resolve_php_bin "$APP" php7.4');

        $this->assertSame($this->dir . '/bin/php7.9', $run->getOutput());
        $this->assertStringContainsString('PHP_BIN=php7.4', $run->getErrorOutput());
    }

    public function test_ohne_passendes_php_bricht_die_suche_ab(): void {
        $this->fakePhp('php', '7.4');

        $run = $this->bash('resolve_php_bin "$APP"');

        $this->assertSame(1, $run->getExitCode());
        $this->assertSame('', $run->getOutput());
        $this->assertStringContainsString('kein PHP 7.9 gefunden', $run->getErrorOutput());
        $this->assertStringContainsString('/bin/php=7.4', $run->getErrorOutput());
    }

    public function test_composer_laeuft_mit_dem_gefundenen_binary(): void {
        $this->fakePhp('php7.9', '7.9');
        $this->executable('composer', "#!/usr/bin/env php\n<?php echo 'mit dem Standard-php gestartet';\n");

        $run = $this->bash('PHP_BIN="$(resolve_php_bin "$APP")"; run_composer install --no-dev');

        $this->assertSame("php7.9 {$this->dir}/bin/composer install --no-dev\n", $run->getOutput(), $run->getErrorOutput());
    }

    public function test_composer_wrapper_waehlt_seinen_interpreter_selbst(): void {
        $this->fakePhp('php7.9', '7.9');
        $this->executable('composer', "#!/bin/sh\necho \"wrapper \$*\"\n");

        $run = $this->bash('PHP_BIN="$(resolve_php_bin "$APP")"; run_composer install');

        $this->assertSame("wrapper install\n", $run->getOutput(), $run->getErrorOutput());
    }

    public function test_skripte_rufen_php_und_composer_nur_ueber_die_erkennung_auf(): void {
        $root = dirname(__DIR__, 3);
        $violations = [];

        foreach (self::SCRIPTS as $pattern) {
            foreach (glob($root . '/' . $pattern) ?: [] as $file) {
                foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
                    if (preg_match('/^\s*#/', $line) === 1) {
                        continue;
                    }
                    if (preg_match('/(?:^|[;&|!]|\$\(|\b(?:if|then|else|do|exec)\b)\s*(?:php|composer)\s/', $line) === 1) {
                        $violations[] = substr($file, strlen($root) + 1) . ':' . ($index + 1) . ': ' . trim($line);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Nackter php-/composer-Aufruf: nimmt die CLI-Vorgabe des Servers statt der Version aus der composer.json. '
                . 'Stattdessen "$PHP_BIN" (resolve_php_bin) bzw. run_composer aus scripts/lib/php-bin.sh.'
        );
    }

    /** Attrappe: meldet auf `-r` ihre Version, sonst ihren Aufruf. */
    private function fakePhp(string $name, string $version): void {
        $this->executable($name, "#!/bin/sh\nif [ \"\$1\" = \"-r\" ]; then echo {$version}; else echo \"{$name} \$*\"; fi\n");
    }

    private function executable(string $name, string $content): void {
        file_put_contents($this->dir . '/bin/' . $name, $content);
        chmod($this->dir . '/bin/' . $name, 0700);
    }

    private function bash(string $script): Process {
        $process = new Process(
            ['bash', '-c', 'set -euo pipefail; source "$LIB"; ' . $script],
            null,
            [
                'PATH' => $this->dir . '/bin:' . getenv('PATH'),
                'LIB' => dirname(__DIR__, 3) . '/scripts/lib/php-bin.sh',
                'APP' => $this->dir . '/app',
            ],
        );
        $process->run();

        return $process;
    }
}
