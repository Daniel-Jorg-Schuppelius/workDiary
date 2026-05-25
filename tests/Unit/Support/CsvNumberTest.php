<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CsvNumber;
use PHPUnit\Framework\TestCase;

class CsvNumberTest extends TestCase {
    public function test_formats_with_de_decimal_separator(): void {
        $this->assertSame('1,50', CsvNumber::decimal(1.5));
    }

    public function test_uses_dot_as_thousand_separator(): void {
        $this->assertSame('1.234,56', CsvNumber::decimal(1234.56));
        $this->assertSame('1.000.000,00', CsvNumber::decimal(1000000));
    }

    public function test_returns_empty_string_for_null_or_empty(): void {
        $this->assertSame('', CsvNumber::decimal(null));
        $this->assertSame('', CsvNumber::decimal(''));
    }

    public function test_respects_custom_decimal_count(): void {
        $this->assertSame('3,142', CsvNumber::decimal(3.14159, 3));
        $this->assertSame('5', CsvNumber::decimal(5, 0));
    }

    public function test_accepts_numeric_string(): void {
        $this->assertSame('42,00', CsvNumber::decimal('42'));
    }
}
