<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : presence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669).
return [
    'board' => 'Ocupación actual',
    'kind' => [
        'do_not_disturb' => 'No molestar',
        'brief_away' => 'Ausente un momento',
        'out_of_office' => 'Fuera de la oficina',
    ],
    'until' => ':kind hasta las :time',
    'validation' => [
        'until_required' => 'Este estado necesita un fin.',
        'until_range' => 'El fin debe estar en el futuro y como máximo a :hours horas.',
    ],
    'dialog' => [
        'title' => 'Mi estado',
        'submit' => 'Fijar estado',
        'hint' => 'Solo se muestra su estado actual, sin motivo y sin historial. Termina a la hora indicada, al fichar la salida o cuando usted lo restablece.',
        'current' => 'Actual',
        'kind' => 'Estado',
        'duration' => 'Durante',
        'no_duration' => 'Sin fin (hasta restablecerlo o fichar la salida)',
        'until' => 'O exactamente hasta',
        'until_hint' => 'Tiene prioridad sobre «Durante». «Ausente un momento» y «Fuera de la oficina» necesitan un fin.',
    ],
    'duration' => [
        '15' => '15 minutos',
        '30' => '30 minutos',
        '60' => '1 hora',
        '120' => '2 horas',
        '240' => '4 horas',
        '480' => '8 horas',
    ],
    'action' => [
        'set' => 'Mi estado',
        'clear' => 'Restablecer estado',
    ],
    'flash' => [
        'set' => 'Su estado «:status» está fijado.',
        'cleared' => 'Su estado se ha restablecido.',
    ],
];
