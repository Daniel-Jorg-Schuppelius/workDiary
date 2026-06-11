<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GermanFederalStateResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Privacy;

use App\Services\Privacy\GermanFederalStateResolver;
use PHPUnit\Framework\TestCase;

class GermanFederalStateResolverTest extends TestCase {
    public function test_resolves_unambiguous_german_postcodes(): void {
        $resolver = new GermanFederalStateResolver;

        $this->assertSame('BW', $resolver->resolve('70173', 'DE')['code'] ?? null);
        $this->assertSame('BY', $resolver->resolve('80331', 'Deutschland')['code'] ?? null);
    }

    public function test_does_not_guess_ambiguous_or_foreign_postcodes(): void {
        $resolver = new GermanFederalStateResolver;

        $this->assertNull($resolver->resolve('14467', 'DE'));
        $this->assertNull($resolver->resolve('70173', 'AT'));
        $this->assertNull($resolver->resolve('invalid', 'DE'));
    }
}
