<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginBinding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use App\Models\Platform\PluginSetting;

/**
 * Plugin-Bindung eines Registry-Jobs: `'*'` = mindestens ein Plugin aktiv,
 * Kommaliste = eines der genannten irgendwo aktiv. Registrar und Wächter
 * teilen die Auflösung; je Prozess wird jede Bindung einmal gefragt.
 *
 * Fail-open wie `ScheduledJobOverride::systemMap()`: der Scheduler wird bei
 * jedem Artisan-Aufruf registriert (`routes/console.php`), also auch bei
 * `migrate:fresh` auf leerer Datenbank oder ohne erreichbaren Server — dann
 * gilt der Job als gebunden und wird wie vor der Vorprüfung angemeldet
 * (Befund E2E 2026-11-02: `no such table: plugin_settings`).
 */
final class PluginBinding {
    /** @var array<string, bool> */
    private static array $memo = [];

    public static function active(?string $binding): bool {
        if ($binding === null || trim($binding) === '') {
            return true;
        }

        return self::$memo[$binding] ??= self::resolve($binding);
    }

    public static function reset(): void {
        self::$memo = [];
    }

    private static function resolve(string $binding): bool {
        try {
            if ($binding === '*') {
                return PluginSetting::anyPluginEnabled();
            }
            foreach (explode(',', $binding) as $pluginId) {
                if (PluginSetting::enabledAnywhere(trim($pluginId))) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return true;
        }
    }
}
