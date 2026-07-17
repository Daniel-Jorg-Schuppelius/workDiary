<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookSignatureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Plugins;

use App\Plugins\Support\WebhookSignature;
use PHPUnit\Framework\TestCase;

/**
 * Golden-Vektoren je Provider-Schema (Konsolidierung B6) — pinnen die
 * Byte-Semantik der Signaturprüfung: Vektoren einmalig via
 * hash_hmac/base64_encode aus Payload+Secret berechnet.
 */
class WebhookSignatureTest extends TestCase {
    private const PAYLOAD = '{"event":"issue.updated","id":42}';

    private const SECRET = 'wd-webhook-secret';

    public function test_github_prefixed_sha256_hex_vector(): void {
        $signature = 'sha256=93e26aca1a9b0994380e1484abcda2acb403c67ba7da45270741f3645df28451';

        $this->assertTrue(WebhookSignature::hmacValid(self::PAYLOAD, self::SECRET, $signature, 'sha256', 'sha256='));
        // Ohne Prefix, falscher Prefix oder manipulierter Body ⇒ ungültig.
        $this->assertFalse(WebhookSignature::hmacValid(self::PAYLOAD, self::SECRET, substr($signature, 7), 'sha256', 'sha256='));
        $this->assertFalse(WebhookSignature::hmacValid(self::PAYLOAD, self::SECRET, 'sha1=' . substr($signature, 7), 'sha256', 'sha256='));
        $this->assertFalse(WebhookSignature::hmacValid(self::PAYLOAD . 'x', self::SECRET, $signature, 'sha256', 'sha256='));
    }

    public function test_zammad_prefixed_sha1_hex_vector(): void {
        $signature = 'sha1=f6894ab34fc57c434e19845348950f26c06ceeec';

        $this->assertTrue(WebhookSignature::hmacValid(self::PAYLOAD, self::SECRET, $signature, 'sha1', 'sha1='));
        $this->assertFalse(WebhookSignature::hmacValid(self::PAYLOAD, 'anderes-secret', $signature, 'sha1', 'sha1='));
    }

    public function test_dropbox_raw_sha256_hex_vector(): void {
        $signature = '93e26aca1a9b0994380e1484abcda2acb403c67ba7da45270741f3645df28451';

        $this->assertTrue(WebhookSignature::hmacValid(self::PAYLOAD, self::SECRET, $signature, 'sha256'));
        // Präfixierte Variante darf ohne Prefix-Parameter nicht passieren.
        $this->assertFalse(WebhookSignature::hmacValid(self::PAYLOAD, self::SECRET, 'sha256=' . $signature, 'sha256'));
        $this->assertFalse(WebhookSignature::hmacValid(self::PAYLOAD, self::SECRET, '', 'sha256'));
    }

    public function test_todoist_base64_sha256_vector(): void {
        $signature = 'k+JqyhqbCZQ4DhSEq82irLQDxnun2kUnB0HzZF3yhFE=';

        $this->assertTrue(WebhookSignature::hmacValid(self::PAYLOAD, self::SECRET, $signature, 'sha256', encoding: 'base64'));
        // Hex-Darstellung desselben HMAC ist im base64-Schema ungültig.
        $this->assertFalse(WebhookSignature::hmacValid(self::PAYLOAD, self::SECRET, '93e26aca1a9b0994380e1484abcda2acb403c67ba7da45270741f3645df28451', 'sha256', encoding: 'base64'));
    }

    public function test_empty_or_missing_secret_always_rejects(): void {
        $signature = 'sha256=93e26aca1a9b0994380e1484abcda2acb403c67ba7da45270741f3645df28451';

        $this->assertFalse(WebhookSignature::hmacValid(self::PAYLOAD, '', $signature, 'sha256', 'sha256='));
        $this->assertFalse(WebhookSignature::hmacValid(self::PAYLOAD, null, $signature, 'sha256', 'sha256='));
        $this->assertFalse(WebhookSignature::tokenValid('', 'token'));
        $this->assertFalse(WebhookSignature::tokenValid(null, 'token'));
    }

    public function test_token_valid_requires_exact_match(): void {
        $this->assertTrue(WebhookSignature::tokenValid('wd-channel-token', 'wd-channel-token'));
        $this->assertFalse(WebhookSignature::tokenValid('wd-channel-token', 'wd-channel-Token'));
        $this->assertFalse(WebhookSignature::tokenValid('wd-channel-token', ''));
        $this->assertFalse(WebhookSignature::tokenValid('wd-channel-token', null));
    }
}
