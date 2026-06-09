<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CryptoServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Services\Whistleblowing\WhistleblowingCryptoService;
use RuntimeException;
use Tests\TestCase;

class CryptoServiceTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
    }

    private function svc(): WhistleblowingCryptoService {
        return app(WhistleblowingCryptoService::class);
    }

    public function test_dek_envelope_and_field_roundtrip(): void {
        $svc = $this->svc();
        $dek = $svc->generateDek();

        $wrapped = $svc->wrapDek($dek);
        $this->assertNotSame($dek, base64_decode($wrapped, true), 'DEK darf nicht im Klartext gewrappt sein.');
        $this->assertSame($dek, $svc->unwrapDek($wrapped));

        $cipher = $svc->encryptWithDek('streng geheim', $dek);
        $this->assertStringNotContainsString('streng geheim', $cipher);
        $this->assertSame('streng geheim', $svc->decryptWithDek($cipher, $dek));
    }

    public function test_decrypt_with_wrong_dek_fails(): void {
        $svc = $this->svc();
        $cipher = $svc->encryptWithDek('geheim', $svc->generateDek());

        $this->expectException(RuntimeException::class);
        $svc->decryptWithDek($cipher, $svc->generateDek());
    }

    public function test_missing_module_key_fails_closed(): void {
        config()->set('whistleblowing.key', '');

        $this->expectException(RuntimeException::class);
        $this->svc()->wrapDek($this->svc()->generateDek());
    }
}
