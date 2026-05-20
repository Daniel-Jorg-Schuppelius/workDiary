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
 */
trait HasOptions {
    /**
     * @return list<string|int>
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
        $out = [];
        foreach (self::cases() as $case) {
            // Trait is intentionally usable on enums without HasLabel.
            // @phpstan-ignore-next-line instanceof.alwaysTrue
            $label = $case instanceof HasLabel ? $case->label() : $case->name;
            $out[$case->value] = $label;
        }

        return $out;
    }

    public static function tryFromName(string $name): ?self {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }
}
