<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plugins.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Registered plugin classes
    |--------------------------------------------------------------------------
    |
    | Plugins werden primär per Auto-Discovery geladen: jede Klasse unter
    | app/Plugins/<Name>/<Name>Plugin.php, die App\Plugins\Contracts\Plugin
    | implementiert, wird automatisch registriert (s. App\Plugins\PluginDiscovery).
    | Ein neues Plugin braucht hier also KEINEN Eintrag mehr.
    |
    | Diese Liste ist nur ein Escape-Hatch für Plugins AUSSERHALB von app/Plugins
    | (z. B. aus Composer-Paketen): hier aufgeführte FQCNs werden zusätzlich
    | geladen und mit den entdeckten zusammengeführt (dedupliziert).
    |
    | Aktivierung pro Organisation erfolgt über die plugin_settings-Tabelle
    | (s. /admin/plugins); ENV-Variablen dienen nur als Fallback für
    | Tests/Konsolen-Kontexte ohne UI-Konfiguration.
    */
    'classes' => [
        // App\Plugins\Beispiel\BeispielPlugin::class, // nur für Plugins außerhalb von app/Plugins
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Disable
    |--------------------------------------------------------------------------
    |
    | Anzahl aufeinanderfolgender Boot-/Runtime-/Healthcheck-Fehler eines
    | Plugins, ab der das Plugin global stillgelegt wird (Setzen von
    | plugin_states.disabled_reason). 0 = nie automatisch deaktivieren.
    */
    'auto_disable_threshold' => (int) env('PLUGINS_AUTO_DISABLE_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Per-plugin configuration
    |--------------------------------------------------------------------------
    |
    | Jedes Plugin liefert seine eigenen Config-Defaults/ENV-Fallbacks über eine
    | plugin-eigene `app/Plugins/<Name>/config.php`, die der Plugin-ServiceProvider
    | per `mergeConfigFrom(..., 'plugins.<id>')` einhängt (s. z. B.
    | App\Plugins\OpenProject\OpenProjectServiceProvider). Deshalb stehen hier
    | KEINE per-Plugin-Blöcke mehr — `config('plugins.<id>.*')` funktioniert
    | trotzdem. (ENV ist ohnehin nur Fallback für Tests/Konsole; produktiv kommt
    | die Konfiguration pro Organisation aus plugin_settings.)
    */
];
