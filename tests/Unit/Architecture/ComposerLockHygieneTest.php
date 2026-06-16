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
 * Lock es enthalten, deshalb wird der Test in dieser Umgebung übersprungen.
 */
class ComposerLockHygieneTest extends TestCase {
    private const PRIVATE_PACKAGE = 'daniel-jorg-schuppelius/php-financial-formats';

    public function test_committed_lock_excludes_private_optional_package(): void {
        $root = dirname(__DIR__, 3);

        if (file_exists($root . '/composer.local.json')) {
            $this->markTestSkipped('composer.local.json aktiv (Zahler-Umgebung) — die lokale Lock darf das private Paket enthalten; nicht committen.');
        }

        $lock = json_decode((string) file_get_contents($root . '/composer.lock'), true);
        $names = array_column(
            array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []),
            'name'
        );

        $this->assertNotContains(
            self::PRIVATE_PACKAGE,
            $names,
            'composer.lock darf das private optionale Paket "' . self::PRIVATE_PACKAGE . '" NICHT enthalten '
                . '(siehe AGENTS.md §9.1) — sonst bricht der Deploy für Installationen ohne Repo-Zugriff.'
        );
    }
}
