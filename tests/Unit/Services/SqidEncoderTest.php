<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SqidEncoderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Services;

use App\Services\SqidEncoder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SqidEncoderTest extends TestCase {
    private function encoder(string $salt = 'test-salt'): SqidEncoder {
        return new SqidEncoder(
            salt: $salt,
            minLength: 10,
            alphabet: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            blocklist: [],
        );
    }

    public function test_roundtrip_returns_original_id(): void {
        $enc = $this->encoder();

        foreach ([1, 2, 42, 1000, 999_999_999] as $id) {
            $sqid = $enc->encode('App\\Models\\Customer', $id);
            $this->assertSame($id, $enc->decode('App\\Models\\Customer', $sqid));
        }
    }

    public function test_min_length_is_respected(): void {
        $enc = $this->encoder();
        $sqid = $enc->encode('App\\Models\\Customer', 1);

        $this->assertGreaterThanOrEqual(10, strlen($sqid));
    }

    public function test_same_id_yields_different_sqids_per_model(): void {
        $enc = $this->encoder();

        $a = $enc->encode('App\\Models\\Customer', 1);
        $b = $enc->encode('App\\Models\\Invoice', 1);

        $this->assertNotSame($a, $b);
    }

    public function test_cross_model_decode_returns_null(): void {
        $enc = $this->encoder();

        $sqid = $enc->encode('App\\Models\\Customer', 42);

        $this->assertNull($enc->decode('App\\Models\\Invoice', $sqid));
    }

    public function test_invalid_sqid_returns_null(): void {
        $enc = $this->encoder();

        $this->assertNull($enc->decode('App\\Models\\Customer', ''));
        $this->assertNull($enc->decode('App\\Models\\Customer', '!!!invalid!!!'));
        $this->assertNull($enc->decode('App\\Models\\Customer', '   '));
    }

    public function test_numeric_id_string_is_not_accepted_as_sqid(): void {
        $enc = $this->encoder();

        // Eine reine Ziffernfolge wie "1" oder "42" darf nicht als gültiger
        // Sqid durchgehen – das ist der Kern des „kein Numeric-Fallback".
        $this->assertNull($enc->decode('App\\Models\\Customer', '1'));
        $this->assertNull($enc->decode('App\\Models\\Customer', '42'));
    }

    public function test_zero_or_negative_id_rejected(): void {
        $enc = $this->encoder();

        $this->expectException(InvalidArgumentException::class);
        $enc->encode('App\\Models\\Customer', 0);
    }

    public function test_salt_changes_alphabet(): void {
        $a = $this->encoder('salt-a')->encode('App\\Models\\Customer', 1);
        $b = $this->encoder('salt-b')->encode('App\\Models\\Customer', 1);

        $this->assertNotSame($a, $b);
    }
}
