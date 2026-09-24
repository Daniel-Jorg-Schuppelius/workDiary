<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SqidStableKeyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Support;

use App\Models\Club\ClubMember;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Support\SqidEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sqids hängen am stabilen Schlüssel des Modells, nicht am Klassennamen
 * (MVP-860). Die Erwartungswerte wurden am 2026-09-23 vor der Umstellung mit
 * leerem Salt erzeugt; ändern sie sich, werden ausgegebene Links ungültig.
 */
class SqidStableKeyTest extends TestCase {
    /** @return array<string, array{class-string, int, string}> */
    public static function frozenSqids(): array {
        return [
            'customer 1' => [Customer::class, 1, '7dVi0romq8'],
            'customer 12345' => [Customer::class, 12345, 'DMAord35kQ'],
            'diary entry 7' => [DiaryEntry::class, 7, 'n5Ni5t2Mkb'],
            'user 42' => [User::class, 42, '3lpoUive9t'],
            'invoice 99' => [Invoice::class, 99, 'DqXdZL2IWO'],
            'club member 3' => [ClubMember::class, 3, 'Fuv5GxlUTP'],
        ];
    }

    /** @param class-string $class */
    #[DataProvider('frozenSqids')]
    public function test_sqids_are_unchanged_by_the_morph_map(string $class, int $id, string $expected): void {
        $encoder = new SqidEncoder('', 10, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');

        $this->assertSame($expected, $encoder->encode($class, $id));
        $this->assertSame($id, $encoder->decode($class, $expected));
    }
}
