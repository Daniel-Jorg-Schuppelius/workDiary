<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HtaccessDirectiveRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Direktiven, die Apache in einer `.htaccess` nicht zulässt.
 *
 * Steht eine davon dort, meldet Apache „not allowed here" und beantwortet
 * JEDE Anfrage mit 500 — nicht nur die betroffene Regel fällt aus, sondern die
 * ganze Anwendung. Genau das passierte am 2026-09-14 mit einem
 * `<DirectoryMatch>`, das Punktverzeichnisse auch ohne mod_rewrite sperren
 * sollte. Keine Test-Suite und kein PHP-Linter sieht das; der Fehler zeigt sich
 * erst im Apache-Log der Produktion.
 *
 * Die Liste folgt dem Kontext-Feld der Apache-Doku: nur „server config"
 * und/oder „virtual host", nicht „.htaccess".
 */
final class HtaccessDirectiveRuleTest extends TestCase {
    private const FORBIDDEN = [
        '<Directory', '<DirectoryMatch', '<Location', '<LocationMatch', '<VirtualHost', '<Proxy', '<ProxyMatch',
        'AllowOverride', 'AllowOverrideList', 'Alias', 'AliasMatch', 'ScriptAlias', 'ScriptAliasMatch',
        'DocumentRoot', 'ServerName', 'ServerAlias', 'ServerRoot', 'Listen', 'LoadModule', 'Include', 'IncludeOptional',
        'RewriteMap', 'ProxyPass', 'ProxyPassMatch', 'ProxyPassReverse', 'ErrorLog', 'CustomLog', 'LogLevel',
    ];

    /** @return array<string, array{0: string}> */
    public static function htaccessFiles(): array {
        $root = dirname(__DIR__, 3);

        return [
            'Projektwurzel' => [$root . '/.htaccess'],
            'public' => [$root . '/public/.htaccess'],
        ];
    }

    #[DataProvider('htaccessFiles')]
    public function test_htaccess_contains_no_server_config_only_directive(string $path): void {
        $this->assertFileExists($path);

        $violations = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            foreach (self::FORBIDDEN as $directive) {
                // Ganzes Wort: `Alias` darf `RedirectMatch` o. Ä. nicht treffen,
                // `<Directory` nicht `<DirectoryMatch` doppelt zählen.
                if (preg_match('/^' . preg_quote($directive, '/') . '(\s|>|$)/i', $trimmed) === 1) {
                    $violations[] = sprintf('Zeile %d: %s', $index + 1, trim($line));
                }
            }
        }

        $this->assertSame([], $violations, sprintf(
            "%s enthält Direktiven, die Apache in einer .htaccess verweigert — das legt die GANZE Anwendung mit 500 lahm:\n%s",
            $path,
            implode("\n", $violations),
        ));
    }
}
