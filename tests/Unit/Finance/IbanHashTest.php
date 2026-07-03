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

use CommonToolkit\Helper\Data\BankHelper;
use PHPUnit\Framework\TestCase;

/**
 * Blind-Index der IBAN (Feature 045, „Matching über unverschlüsselte
 * Ableitungen"): normalisierte IBANs müssen denselben Hash ergeben.
 * Paritäts-Wächter für das Toolkit-Format (BankHelper::hashIBAN):
 * Format-Anker ist der SHA-256-Hex-Digest der normalisierten IBAN —
 * an ihm hängen die persistierten Blindindizes (iban_hash,
 * statement_iban_hash, counterparty_iban_hash). Ändert das Toolkit
 * Normalisierung oder Digest-Format, schlägt dieser Test an.
 */
class IbanHashTest extends TestCase {
    public function test_normalize_removes_spaces_and_uppercases(): void {
        $this->assertSame('DE89370400440532013000', BankHelper::normalizeIBAN(' de89 3704 0044 0532 0130 00 '));
    }

    public function test_hash_is_stable_across_formatting(): void {
        $spaced = BankHelper::hashIBAN('DE89 3704 0044 0532 0130 00');
        $compact = BankHelper::hashIBAN('de89370400440532013000');

        $this->assertNotNull($spaced);
        $this->assertSame($spaced, $compact);
    }

    public function test_hash_format_anchor_is_sha256_hex(): void {
        // Pinnt das persistierte Format: sha256-Hex der normalisierten IBAN.
        $this->assertSame(
            hash('sha256', 'DE89370400440532013000'),
            BankHelper::hashIBAN(' de89 3704 0044 0532 0130 00 '),
        );
    }

    public function test_null_and_empty_yield_null(): void {
        $this->assertNull(BankHelper::hashIBAN(null));
        $this->assertNull(BankHelper::hashIBAN('   '));
        $this->assertNull(BankHelper::normalizeIBAN(null));
    }
}
