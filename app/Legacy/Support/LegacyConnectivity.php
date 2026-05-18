<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyConnectivity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Support;

use App\Support\DatabaseHealth;

/**
 * Dünner Wrapper über {@see DatabaseHealth} für die legacy-Verbindung.
 * Existiert aus Lesbarkeitsgründen — Aufrufer können `LegacyConnectivity::isAvailable()`
 * lesen, ohne sich den Connection-Namen merken zu müssen.
 */
final class LegacyConnectivity
{
    private const CONNECTION = 'legacy';

    public static function isAvailable(): bool
    {
        if (! filled(config('database.connections.legacy.database'))) {
            return false;
        }

        return DatabaseHealth::isAvailable(self::CONNECTION);
    }

    public static function markUnavailable(): void
    {
        DatabaseHealth::markUnavailable(self::CONNECTION);
    }

    /**
     * @template T
     *
     * @param  callable():T  $work
     * @param  T  $default
     * @return T
     */
    public static function attempt(callable $work, mixed $default): mixed
    {
        if (! filled(config('database.connections.legacy.database'))) {
            return $default;
        }

        return DatabaseHealth::attempt(self::CONNECTION, $work, $default);
    }

    public static function reset(): void
    {
        DatabaseHealth::reset(self::CONNECTION);
    }
}
