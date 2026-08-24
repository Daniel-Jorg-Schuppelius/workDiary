<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnvExampleRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für .env.example (Vollscan 2026-08-23, J13): Vier
 * TIMESHEET-Schlüssel trugen Namen, die kein env()-Aufruf je las — Betreiber
 * setzten sie wirkungslos. Jeder Schlüssel der Vorlage muss irgendwo gelesen
 * werden (config/, Plugin-configs, Framework-/Toolkit-Konfiguration, Vite).
 */
class EnvExampleRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Schlüssel → wo er außerhalb von config/ gelesen wird */
    private const READ_ELSEWHERE = [
        'BCRYPT_ROUNDS' => 'vendor/laravel/framework/config/hashing.php',
        'ERROR_TOOLKIT_LOG_CHANNEL' => 'vendor/dschuppelius/php-error-toolkit/config/error-toolkit.php',
        'VITE_APP_NAME' => 'Vite-Client (import.meta.env), kein PHP-Leser',
    ];

    public function test_every_example_key_has_a_reader(): void {
        $root = $this->repoRoot();
        $example = (string) file_get_contents($root . '/.env.example');
        preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $example, $matches);
        $keys = array_values(array_unique($matches[1]));

        $readers = [];
        $files = array_merge($this->phpFiles('config'), $this->filesUnder('app/Plugins', '/^config\.php$/'), $this->phpFiles('bootstrap'));
        foreach ($files as $file) {
            preg_match_all('/env\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]/', (string) file_get_contents($file), $found);
            foreach ($found[1] as $key) {
                $readers[$key] = true;
            }
        }

        $orphans = [];
        foreach ($keys as $key) {
            if (! isset($readers[$key]) && ! isset(self::READ_ELSEWHERE[$key])) {
                $orphans[] = $key;
            }
        }

        $this->assertSame([], $orphans, ".env.example enthält Schlüssel ohne env()-Leser (falscher Name oder verwaist):\n"
            . implode("\n", $orphans) . "\n\nSchlüssel umbenennen/entfernen oder — wenn ein Paket ihn liest — in READ_ELSEWHERE eintragen.");
    }
}
