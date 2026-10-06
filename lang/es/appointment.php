<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : appointment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Eigene Gruppe: die losen Schlüssel „bestätigt“/„storniert“ gehören der Buchhaltung (acknowledged/reversed).
return [
    'status' => [
        'requested' => 'solicitada',
        'confirmed' => 'confirmada',
        'declined' => 'rechazada',
        'canceled' => 'cancelada',
        'superseded' => 'reemplazada',
    ],
];
