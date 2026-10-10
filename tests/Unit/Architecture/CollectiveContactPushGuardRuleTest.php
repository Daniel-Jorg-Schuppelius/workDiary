<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CollectiveContactPushGuardRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-1109: Jede Implementierung von `pushContact()` bzw.
 * `pushSupplierContact()` eines Kontakt-Syncers prüft zuerst
 * `CollectiveContacts::assertPushable()`. Ein Sammelkontakt steht für den
 * Sammelkontakt des Zielsystems und wird nie als eigener Kontakt angelegt.
 */
class CollectiveContactPushGuardRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_every_contact_push_guards_collective_contacts(): void {
        $missing = [];
        foreach ($this->phpFiles('app/Plugins') as $path) {
            $source = (string) file_get_contents($path);
            if (preg_match('/implements[^{]*\b(ContactSyncer|SupplierContactSyncer)\b/', $this->stripComments($source)) !== 1) {
                continue;
            }
            if (preg_match_all('/function (pushContact|pushSupplierContact)\([^)]*\)\s*:\s*string\s*\{/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[0] as [$signature, $offset]) {
                $body = substr($source, $offset + strlen($signature), 400);
                if (! str_contains($body, 'CollectiveContacts::assertPushable(')) {
                    $missing[] = $this->relativePath($path) . ':' . $this->lineOf($source, $offset);
                }
            }
        }

        $this->assertSame([], $missing, 'Kontakt-Push ohne Sammelkontakt-Wächter (CollectiveContacts::assertPushable() als erste Anweisung): ' . implode(', ', $missing));
    }
}
