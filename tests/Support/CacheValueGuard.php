<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CacheValueGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use UnitEnum;

/**
 * Findet Objekte in Cache-Werten (rekursiv). Der Cache liest Objekte nicht
 * zurück (`cache.serializable_classes` = false), Enums schon.
 */
final class CacheValueGuard {
    /** @return class-string|null Klasse des ersten Objekts, null = nur Arrays, Skalare und Enums */
    public static function objectIn(mixed $value): ?string {
        if ($value instanceof UnitEnum) {
            return null;
        }
        if (is_object($value)) {
            return $value::class;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                $class = self::objectIn($item);
                if ($class !== null) {
                    return $class;
                }
            }
        }

        return null;
    }
}
