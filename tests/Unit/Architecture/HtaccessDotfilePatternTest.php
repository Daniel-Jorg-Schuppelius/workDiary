<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HtaccessDotfilePatternTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Das Punktdatei-Muster der `.htaccess` in beide Richtungen.
 *
 * Die Audit-Korrektur config-5 machte aus `^\.$` das Muster `^\..*$`. Das
 * sperrte `.env.backup` und `.htpasswd` — aber auch `/.well-known/security.txt`,
 * den Meldekanal für Sicherheitslücken: Gibt es kein echtes Verzeichnis
 * `.well-known`, hält Apache diesen Pfadteil für den Dateinamen, und das Muster
 * trifft. Nachgestellt am 2026-09-14 mit einer lokalen Apache-2.4-Instanz.
 *
 * Apache nutzt PCRE; `preg_match` bildet das Muster deshalb treu nach.
 */
final class HtaccessDotfilePatternTest extends TestCase {
    private function dotfilePattern(): string {
        $content = (string) file_get_contents(dirname(__DIR__, 3) . '/.htaccess');
        preg_match_all('/<FilesMatch "([^"]+)">/', $content, $matches);
        $dotfile = array_values(array_filter($matches[1], static fn (string $pattern): bool => str_starts_with($pattern, '^\\.')));

        $this->assertCount(1, $dotfile, 'Genau ein FilesMatch für Punktdateien erwartet.');

        return '~' . $dotfile[0] . '~';
    }

    /** @return array<string, array{0: string}> */
    public static function blockedNames(): array {
        return [
            '.env' => ['.env'],
            '.env.backup' => ['.env.backup'],
            '.htpasswd' => ['.htpasswd'],
            '.htaccess' => ['.htaccess'],
            '.git' => ['.git'],
            '.well-known-Imitat' => ['.well-known-backup'],
        ];
    }

    /** @return array<string, array{0: string}> */
    public static function reachableNames(): array {
        return [
            '.well-known' => ['.well-known'],
            'index.php' => ['index.php'],
            'security.txt' => ['security.txt'],
        ];
    }

    #[DataProvider('blockedNames')]
    public function test_dotfiles_stay_blocked(string $name): void {
        $this->assertSame(1, preg_match($this->dotfilePattern(), $name), "{$name} muss gesperrt sein.");
    }

    #[DataProvider('reachableNames')]
    public function test_the_security_contact_stays_reachable(string $name): void {
        $this->assertSame(0, preg_match($this->dotfilePattern(), $name), "{$name} darf nicht gesperrt sein — sonst ist security.txt unerreichbar.");
    }
}
