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
    | Zeitfenster für den Auto-Disable (Review 2026-08, W2b): gezählt werden nur
    | Fehler innerhalb des Fensters — fünf Fehler über sechs Monate verteilt
    | legen das Plugin nicht mehr still. 0 = kumulativ (altes Verhalten).
    */
    'auto_disable_window_hours' => (int) env('PLUGINS_AUTO_DISABLE_WINDOW_HOURS', 24),

    /*
    | Eigener (typischerweise niedrigerer) Schwellwert für Boot-Fehler — ein
    | Plugin, das den Boot reißt, ist gravierender als ein Netz-Timeout.
    | null = auto_disable_threshold gilt auch für Boot.
    */
    'auto_disable_boot_threshold' => env('PLUGINS_AUTO_DISABLE_BOOT_THRESHOLD') !== null
        ? (int) env('PLUGINS_AUTO_DISABLE_BOOT_THRESHOLD')
        : null,

    /*
    |--------------------------------------------------------------------------
    | Healthchecks (Review 2026-08, W3)
    |--------------------------------------------------------------------------
    |
    | health_flap_threshold : Statuswechsel werden erst nach N gleichen
    |   Ergebnissen in Folge gemeldet (Hysterese gegen Flapping). Der allererste
    |   Status eines Plugins wird immer sofort gemeldet.
    | health_timeout_seconds: Timeout-Budget je Check — im Health-Kontext
    |   erzeugte HTTP-Clients bekommen dieses Timeout und höchstens 1 Retry.
    | health_exclude        : Plugin-IDs, die der geplante Lauf überspringt.
    */
    'health_flap_threshold' => (int) env('PLUGINS_HEALTH_FLAP_THRESHOLD', 2),
    'health_timeout_seconds' => (int) env('PLUGINS_HEALTH_TIMEOUT_SECONDS', 10),
    'health_exclude' => array_values(array_filter(explode(',', (string) env('PLUGINS_HEALTH_EXCLUDE', '')))),

    /*
    |--------------------------------------------------------------------------
    | Aufbewahrung plugin_errors (Review 2026-08, W2c)
    |--------------------------------------------------------------------------
    |
    | model:prune (Scheduler, täglich) entfernt quittierte Fehler nach
    | `errors_retention_acknowledged_days` und offene nach
    | `errors_retention_open_days` Tagen.
    */
    'errors_retention_acknowledged_days' => (int) env('PLUGINS_ERRORS_RETENTION_ACKNOWLEDGED_DAYS', 30),
    'errors_retention_open_days' => (int) env('PLUGINS_ERRORS_RETENTION_OPEN_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Ziele im privaten Netz (SSRF-Opt-in für Kern-Dienste)
    |--------------------------------------------------------------------------
    |
    | `PluginHttpFactory` weist Ziele in privaten/reservierten Netzen ab
    | (Sicherheitsscan 2026-08-23, S-10). Plugins haben dafür die
    | Org-Einstellung `allow_private_network`; Kern-Dienste ohne
    | Plugin-Einstellungen — etwa ein selbst gehostetes Nominatim oder OSRM —
    | werden hier vom Betreiber freigegeben. Komma-getrennte Dienst-IDs,
    | z. B. `nominatim,osrm`. Leer lassen, wenn alle Ziele öffentlich sind.
    */
    'private_network_targets' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PLUGINS_PRIVATE_NETWORK_TARGETS', ''))
    ))),

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
