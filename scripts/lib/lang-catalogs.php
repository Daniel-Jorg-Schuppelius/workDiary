<?php
/*
 * scripts/lib/lang-catalogs.php
 *
 * Übersetzungskataloge ohne Framework, für translations-diff.php und
 * translations-coverage.php. Gegenstück zur Laufzeit: Translations::catalogs().
 */

declare(strict_types=1);

/**
 * Kern (`''` → lang/) und je Plugin `app/Plugins/<Name>/Resources/lang` mit der
 * Plugin-ID (`XxxPlugin::ID`) als Namespace, wie PluginServiceProviderBase sie lädt.
 *
 * @return array<string, string>
 */
function langCatalogs(string $base): array {
    $plugins = [];
    foreach (glob($base . '/app/Plugins/*/Resources/lang', GLOB_ONLYDIR) ?: [] as $dir) {
        foreach (glob(dirname($dir, 2) . '/*Plugin.php') ?: [] as $class) {
            if (preg_match("/const ID = '([^']+)'/", (string) file_get_contents($class), $m) === 1) {
                $plugins[$m[1]] = $dir;
            }
        }
    }
    ksort($plugins);

    return ['' => $base . '/lang'] + $plugins;
}
