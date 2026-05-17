<?php

/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FacadesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Support\Toolkit;

use App\Support\Toolkit\NumberFacade;
use App\Support\Toolkit\StringFacade;
use CommonToolkit\Enums\CountryCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FacadesTest extends TestCase
{
    #[Test]
    public function string_facade_truncates(): void
    {
        self::assertSame('hello...', StringFacade::truncate('hello world', 8));
    }

    #[Test]
    public function string_facade_is_null_or_empty(): void
    {
        self::assertTrue(StringFacade::isNullOrEmpty(null));
        self::assertTrue(StringFacade::isNullOrEmpty(''));
        self::assertFalse(StringFacade::isNullOrEmpty('x'));
    }

    #[Test]
    public function string_facade_returns_dash_for_empty_initials(): void
    {
        self::assertSame('—', StringFacade::printableInitials(null));
        self::assertSame('—', StringFacade::printableInitials('   '));
    }

    #[Test]
    public function string_facade_builds_initials(): void
    {
        self::assertSame('M.S.', StringFacade::printableInitials('Max Schuppelius'));
        self::assertSame('A.B.G.', StringFacade::printableInitials('Alpha Beta Gamma', 3));
    }

    #[Test]
    public function number_facade_parses_german_decimal(): void
    {
        self::assertSame(1234.56, NumberFacade::parseDecimal('1.234,56', CountryCode::Germany));
        self::assertSame(0.0, NumberFacade::parseDecimal(''));
    }
}
