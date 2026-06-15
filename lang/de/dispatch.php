<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dispatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'heading' => 'Disposition',
    'badge_prefix' => 'Disposition',
    'set_status' => ':status',
    'override_reason' => 'Begründung der Übersteuerung',
    'override_placeholder' => 'Warum trotz Konflikt bestätigen?',
    'conflicts' => [
        'hard' => 'Harte Konflikte',
        'soft' => 'Warnungen',
        'none' => 'Keine Konflikte für diese Zuweisung.',
    ],
    'vehicle' => [
        'heading' => 'Fahrzeug-Reservierung',
        'label' => 'Fahrzeug',
        'from' => 'Von',
        'to' => 'Bis',
        'reserve' => 'Reservieren',
        'release' => 'Aufheben',
        'none' => 'Keine Fahrzeug-Reservierung für diesen Auftrag.',
    ],
    'reservations' => [
        'title' => 'Fahrzeug-Reservierungen',
        'subtitle' => 'Reservierungen je Fahrzeug verwalten.',
        'all_vehicles' => 'Alle Fahrzeuge',
        'reserved_by' => 'Reserviert von',
        'empty' => 'Keine Reservierungen vorhanden.',
    ],
];
