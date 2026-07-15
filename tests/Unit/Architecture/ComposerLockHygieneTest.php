<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComposerLockHygieneTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Schützt die committete composer.lock vor dem optionalen, privaten Paket
 * `daniel-jorg-schuppelius/php-financial-formats` (siehe AGENTS.md §9.1).
 *
 * Steht es in der Lock, bricht `composer install` bei jeder Installation OHNE
 * Zugriff auf das private Repo (HTTP 404). Zahlende Entwickler/Server binden es
 * lokal über eine gitignored `composer.local.json` ein — dort darf die LOKALE
 * Lock es enthalten; geprüft wird dann der committete Stand (`git show`), damit
 * ein versehentlich committeter Verstoß auch in der Zahler-Umgebung rot wird.
 */
class ComposerLockHygieneTest extends TestCase {
    private const PRIVATE_PACKAGE = 'daniel-jorg-schuppelius/php-financial-formats';

    public function test_committed_lock_excludes_private_optional_package(): void {
        $root = dirname(__DIR__, 3);

        if (! $this->lockContainsPackage((string) file_get_contents($root . '/composer.lock'))) {
            $this->assertTrue(true); // Arbeits-Lock sauber — nichts zu beanstanden.

            return;
        }

        // Arbeits-Lock enthält das Paket. In der Zahler-Umgebung (composer.local.json)
        // ist das lokal legitim — der Verstoß liegt erst vor, wenn auch die
        // COMMITTETE Lock das Paket führt.
        if (! file_exists($root . '/composer.local.json')) {
            $this->fail(
                'composer.lock enthält das private optionale Paket "' . self::PRIVATE_PACKAGE . '" '
                    . '(siehe AGENTS.md §9.1) — sonst bricht der Deploy für Installationen ohne Repo-Zugriff.'
            );
        }

        $committed = @shell_exec('git -C ' . escapeshellarg($root) . ' show HEAD:composer.lock 2>/dev/null');

        if (! is_string($committed) || trim($committed) === '') {
            $this->markTestSkipped('Lokale Lock enthält das Paket (Zahler-Umgebung, legitim) — committete Lock ohne Git nicht prüfbar.');
        }

        $this->assertFalse(
            $this->lockContainsPackage($committed),
            'Die COMMITTETE composer.lock (git HEAD) enthält das private optionale Paket "' . self::PRIVATE_PACKAGE . '" '
                . '(siehe AGENTS.md §9.1) — die Lock-Änderung aus der Zahler-Umgebung darf nicht committet werden.'
        );
    }

    private function lockContainsPackage(string $lockJson): bool {
        $lock = json_decode($lockJson, true);
        $names = array_column(
            array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []),
            'name'
        );

        return in_array(self::PRIVATE_PACKAGE, $names, true);
    }

    public function test_composer_json_keeps_private_package_optional(): void {
        $root = dirname(__DIR__, 3);
        $json = json_decode((string) file_get_contents($root . '/composer.json'), true);

        $this->assertArrayNotHasKey(
            self::PRIVATE_PACKAGE,
            $json['require'] ?? [],
            'composer.json darf "' . self::PRIVATE_PACKAGE . '" NICHT in require führen — Einbindung nur über composer.local.json (AGENTS.md §9.1).'
        );
        $this->assertArrayNotHasKey(
            self::PRIVATE_PACKAGE,
            $json['require-dev'] ?? [],
            'composer.json darf "' . self::PRIVATE_PACKAGE . '" NICHT in require-dev führen — Einbindung nur über composer.local.json (AGENTS.md §9.1).'
        );
        $this->assertStringNotContainsString(
            'php-financial-formats',
            json_encode($json['repositories'] ?? []),
            'Die private VCS-Quelle für php-financial-formats gehört in composer.local.json, nicht in die committete composer.json (AGENTS.md §9.1).'
        );
        $this->assertArrayHasKey(
            self::PRIVATE_PACKAGE,
            $json['suggest'] ?? [],
            'composer.json soll "' . self::PRIVATE_PACKAGE . '" als suggest-Eintrag dokumentieren (AGENTS.md §9.1).'
        );
    }
}
