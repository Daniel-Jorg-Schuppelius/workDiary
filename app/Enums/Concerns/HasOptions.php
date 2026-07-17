<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasOptions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Concerns;

use App\Enums\Contracts\HasLabel;
use BackedEnum;

/**
 * Convenience helpers shared by all backed enums in App\Enums.
 *
 * Requires the using enum to be a BackedEnum. If the enum implements
 * App\Enums\Contracts\HasLabel, options() returns value => label() pairs.
 *
 * @phpstan-require-implements BackedEnum
 *
 * @method static list<static> cases()
 */
trait HasOptions {
    /**
     * @return list<value-of<static>>
     */
    public static function values(): array {
        return array_map(static fn(BackedEnum $case) => $case->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function names(): array {
        return array_map(static fn(BackedEnum $case) => $case->name, self::cases());
    }

    /**
     * Map of value => human-readable label, for <select> options.
     *
     * @return array<string|int, string>
     */
    public static function options(): array {
        return array_combine(
            self::values(),
            array_map(self::optionLabel(...), self::cases()),
        );
    }

    /**
     * Resolve the human-readable label for a single case.
     *
     * Typed against BackedEnum on purpose so the HasLabel check stays
     * meaningful for enums that do and do not implement the contract.
     */
    private static function optionLabel(BackedEnum $case): string {
        return $case instanceof HasLabel ? $case->label() : $case->name;
    }

    public static function tryFromName(string $name): ?self {
        foreach (self::cases() as $case) {
            if (self::caseName($case) === $name) {
                return $case;
            }
        }

        return null;
    }

    private static function caseName(BackedEnum $case): string {
        return $case->name;
    }
}
