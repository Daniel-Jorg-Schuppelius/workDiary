<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IbanHashTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Finance;

use App\Support\Iban;
use PHPUnit\Framework\TestCase;

/**
 * Blind-Index der IBAN (Feature 045, „Matching über unverschlüsselte
 * Ableitungen"): normalisierte IBANs müssen denselben Hash ergeben.
 */
class IbanHashTest extends TestCase {
    public function test_normalize_removes_spaces_and_uppercases(): void {
        $this->assertSame('DE89370400440532013000', Iban::normalize(' de89 3704 0044 0532 0130 00 '));
    }

    public function test_hash_is_stable_across_formatting(): void {
        $spaced = Iban::hash('DE89 3704 0044 0532 0130 00');
        $compact = Iban::hash('de89370400440532013000');

        $this->assertNotNull($spaced);
        $this->assertSame($spaced, $compact);
    }

    public function test_null_and_empty_yield_null(): void {
        $this->assertNull(Iban::hash(null));
        $this->assertNull(Iban::hash('   '));
        $this->assertNull(Iban::normalize(null));
    }
}
