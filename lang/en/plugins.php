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
        'facturation' => 'Document handover',
        'mirror' => 'File mirroring',
        'inventory' => 'Inventory write-back',
    ],

    'compatibility' => [
        'incompatible' => 'Incompatible',
        'range' => 'Core :min–:max',
        'range_hint' => 'Supported WorkDiary core version range.',
        'activation_blocked' => 'Plugin cannot be activated: :message',
    ],
];
