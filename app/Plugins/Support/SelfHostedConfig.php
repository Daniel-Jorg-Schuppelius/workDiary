<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfHostedConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

/**
 * Gemeinsame Regeln der `…Config`-Klassen selbst gehosteter Quellen (Phase
 * 158; Konsolidierung kon-05): die Basis-URL aus den Plugin-Einstellungen
 * und die Lesegrenzen aus der Betreiber-Konfiguration `plugins.<id>.*`.
 * Welche Schlüssel ein Plugin sonst liest, bleibt in seiner Config.
 */
final class SelfHostedConfig {
    /** Basis-URL der Instanz: getrimmt und ohne Schrägstrich am Ende; null, wenn leer. */
    public static function baseUrl(PluginSettingsResolver $settings, string $key = 'base_url'): ?string {
        $url = $settings->string($key, trim: true);

        return $url !== null ? rtrim($url, '/') : null;
    }

    /** Einträge je Seite, zwischen 1 und dem Höchstwert der API. */
    public static function pageSize(string $pluginId, int $default, int $cap): int {
        return max(1, min($cap, (int) config('plugins.' . $pluginId . '.page_size', $default)));
    }

    /** Seiten, die ein Lesen höchstens holt — darüber gilt es als unvollständig ({@see TaskSync\RemotePages}). */
    public static function maxPages(string $pluginId, int $default, string $key = 'max_pages'): int {
        return max(1, (int) config('plugins.' . $pluginId . '.' . $key, $default));
    }
}
