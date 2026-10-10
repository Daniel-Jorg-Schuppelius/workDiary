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
        'facturation' => 'Entrega de documentos',
        'mirror' => 'Duplicado de archivos',
        'inventory' => 'Reescritura de existencias',
    ],

    'compatibility' => [
        'incompatible' => 'Incompatible',
        'range' => 'Núcleo :min–:max',
        'range_hint' => 'Rango de versiones del núcleo de WorkDiary compatible.',
        'activation_blocked' => 'El plugin no se puede activar: :message',
    ],

    // Adressprüfung ausgehender Plugin-Ziele (UrlSafety, PluginHttpFactory).
    'url_guard' => [
        'invalid' => ':prefix: :subject no es una dirección http(s) válida.',
        'private' => ':prefix: :subject apunta a una dirección privada/interna.',
        'subject' => [
            'base' => 'La URL base',
            'target' => 'La URL de destino',
        ],
        'hint_default' => 'Las direcciones privadas deben permitirse expresamente.',
        'hint_setting' => 'Para una instancia en su propia red, active el ajuste del plugin «:setting».',
        'hint_operator' => 'Solo el operador de su instalación puede permitir un destino en su propia red (PLUGINS_PRIVATE_NETWORK_TARGETS).',
    ],
];
