<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plugins.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Fähigkeiten, die über eigene Registries laufen statt über das
    // Capability-Enum (Entscheid 2026-08-21 zum Audit-Befund W1.6):
    // nur für die Anzeige, kein Vertragsbestandteil.
    'capability' => [
        'facturation' => 'Belegübergabe',
        'mirror' => 'Dateispiegelung',
        'inventory' => 'Bestands-Rückschrieb',
    ],

    'compatibility' => [
        'incompatible' => 'Inkompatibel',
        'range' => 'Kern :min–:max',
        'range_hint' => 'Unterstützter WorkDiary-Kernversionsbereich.',
        'activation_blocked' => 'Plugin kann nicht aktiviert werden: :message',
    ],

    // Adressprüfung ausgehender Plugin-Ziele (UrlSafety, PluginHttpFactory).
    'url_guard' => [
        'invalid' => ':prefix: :subject ist keine gültige http(s)-Adresse.',
        'private' => ':prefix: :subject zeigt auf eine private/interne Adresse.',
        'subject' => [
            'base' => 'Die Basis-URL',
            'target' => 'Die Ziel-URL',
        ],
        'hint_default' => 'Die Freigabe privater Adressen muss ausdrücklich aktiviert werden.',
        'hint_setting' => 'Für eine Instanz im eigenen Netz die Plugin-Einstellung „:setting“ aktivieren.',
        'hint_operator' => 'Ein Ziel im eigenen Netz kann nur der Betreiber Ihrer Installation freigeben (PLUGINS_PRIVATE_NETWORK_TARGETS).',
    ],
];
