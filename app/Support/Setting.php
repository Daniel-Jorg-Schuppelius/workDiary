<?php
/*
 * Created on   : Fri May 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Setting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;

/**
 * Mandantenbewusster Konfigurationszugriff.
 *
 * Auflösungsreihenfolge:
 *   1. Organization::settings[<group>][<rest>], sofern eine aktive Organisation gebunden ist
 *   2. config('<group>.<rest>') (dateibasierter Default, env-überschreibbar)
 *   3. $default (harter Fallback)
 *
 * Beispiel:  Setting::get('pagination.customers', 25)
 */
final class Setting {
    public static function get(string $key, mixed $default = null): mixed {
        [$group, $rest] = array_pad(explode('.', $key, 2), 2, null);
        if ($group === null || $rest === null || $rest === '') {
            return config($key, $default);
        }

        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                /** @var array<string, mixed> $settings */
                $settings = (array) ($org->settings ?? []);
                /** @var array<string, mixed> $stored */
                $stored = (array) ($settings[$group] ?? []);
                $value = data_get($stored, $rest, \INF);
                if ($value !== \INF) {
                    return $value;
                }
            }
        }

        return config($key, $default);
    }
}
