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
];
