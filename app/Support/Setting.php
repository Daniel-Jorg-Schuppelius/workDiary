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

use App\Models\{Organization, SystemSetting};
use App\Settings\{SettingScope, SettingsRegistry};

/**
 * Mandantenbewusster Konfigurationszugriff.
 *
 * Auflösungsreihenfolge (Feature 067, MVP-173):
 *   1. Organization::settings[<group>][<rest>], sofern eine aktive Organisation gebunden ist
 *   2. system_settings (systemweiter Betreiber-Override, UI-schreibbar)
 *   3. config('<group>.<rest>') (dateibasierter Default, env-überschreibbar)
 *   4. $default (harter Fallback)
 *
 * Beispiel:  Setting::get('pagination.customers', 25)
 *
 * Schreiben ausschließlich über Setting::set()/reset() (validiert gegen
 * die Settings-Registry) — nie direkt in die Ablagen.
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

        $systemValues = SystemSetting::valueMap();
        if (array_key_exists($key, $systemValues)) {
            return $systemValues[$key];
        }

        return config($key, $default);
    }

    /**
     * Validierter Schreibweg über die Settings-Registry (nur registrierte
     * Keys; System- oder Organisations-Scope).
     */
    public static function set(string $key, mixed $value, SettingScope $scope, ?Organization $organization = null, ?int $userId = null): void {
        app(SettingsRegistry::class)->set($key, $value, $scope, $organization, $userId);
    }

    /** Entfernt den Override der Ebene (Rollback auf Default). */
    public static function reset(string $key, SettingScope $scope, ?Organization $organization = null): void {
        app(SettingsRegistry::class)->reset($key, $scope, $organization);
    }
}
