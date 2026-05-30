<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SqidHelperTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Support;

use App\Models\Customer;
use App\Services\SqidEncoder;
use App\Support\Sqid;
use Tests\TestCase;

class SqidHelperTest extends TestCase {
    private function encode(int $id): string {
        return app(SqidEncoder::class)->encode(Customer::class, $id);
    }

    public function test_decode_or_numeric_decodes_a_valid_sqid(): void {
        $sqid = $this->encode(42);

        $this->assertSame(42, Sqid::decodeOrNumeric(Customer::class, $sqid));
    }

    public function test_decode_or_numeric_falls_back_to_raw_numeric_id(): void {
        // Backward-Compat: alte Links/Bookmarks mit roher numerischer ID.
        $this->assertSame(7, Sqid::decodeOrNumeric(Customer::class, '7'));
        $this->assertSame(7, Sqid::decodeOrNumeric(Customer::class, 7));
    }

    public function test_decode_or_numeric_returns_default_for_invalid_input(): void {
        $this->assertNull(Sqid::decodeOrNumeric(Customer::class, ''));
        $this->assertNull(Sqid::decodeOrNumeric(Customer::class, null));
        $this->assertNull(Sqid::decodeOrNumeric(Customer::class, 'not-a-sqid!'));
        $this->assertSame(0, Sqid::decodeOrNumeric(Customer::class, '', 0));
    }

    public function test_decode_or_numeric_normalizes_non_positive_to_default(): void {
        // "0" ist numerisch, aber keine gültige PK → Default greift.
        $this->assertNull(Sqid::decodeOrNumeric(Customer::class, '0'));
        $this->assertNull(Sqid::decodeOrNumeric(Customer::class, '-5'));
        $this->assertSame(99, Sqid::decodeOrNumeric(Customer::class, '0', 99));
    }

    public function test_decode_or_numeric_does_not_cross_decode_other_models(): void {
        $sqid = app(SqidEncoder::class)->encode(\App\Models\User::class, 5);

        // Sqid eines fremden Modells ist keine gültige Customer-Sqid und auch
        // nicht numerisch → Default.
        $this->assertNull(Sqid::decodeOrNumeric(Customer::class, $sqid));
    }
}
