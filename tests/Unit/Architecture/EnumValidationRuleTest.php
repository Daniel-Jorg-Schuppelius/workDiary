<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnumValidationRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-1393/1398 (Konsolidierungs-Audit 2026-10-23, B5): Ein Enum-Feld
 * validiert über `Rule::enum(X::class)`, nicht über eine aus `cases()`
 * zusammengesetzte `in`-Liste.
 */
class EnumValidationRuleTest extends TestCase {
    use ScansSourceTree;

    private const PATTERN = '/(?:\'in:\'\s*\.\s*implode\(\s*\',\'\s*,\s*|Rule::in\(\s*)array_column\(\s*[\\\\\w]+::cases\(\)/';

    public function test_enum_fields_validate_with_rule_enum(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all(self::PATTERN, $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [, $offset]) {
                    $violations[] = sprintf('%s:%d', $this->relativePath($file), $this->lineOf($source, (int) $offset));
                }
            }
        }

        $this->assertSame([], $violations, "Enum-Regel aus cases() gebaut — Rule::enum(X::class) nutzen:\n" . implode("\n", $violations));
    }

    public function test_the_pattern_recognizes_both_forms(): void {
        $this->assertSame(1, preg_match(self::PATTERN, "'in:' . implode(',', array_column(AccountType::cases(), 'value'))"));
        $this->assertSame(1, preg_match(self::PATTERN, "Rule::in(array_column(\\App\\Enums\\X::cases(), 'value'))"));
        $this->assertSame(0, preg_match(self::PATTERN, 'array_fill_keys(array_column(Priority::cases(), \'value\'), 0)'));
    }
}
