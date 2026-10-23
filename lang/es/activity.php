<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : activity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Aktivitäts-Feed (Feature 183).
return [
    'title' => 'Actividad',
    'subtitle' => 'Quién cambió qué y cuándo · :range',
    'subject_title' => 'Historial de cambios',
    'more' => 'Las entradas anteriores están en el registro de auditoría.',
    'more_in_audit' => 'Entradas anteriores en el registro de auditoría',
    'newer' => 'Más recientes',
    'older' => 'Anteriores',
    'filter' => [
        'type' => 'Tipo de objeto',
        'all_types' => 'Todos los tipos de objeto',
        'user' => 'Persona',
        'all_users' => 'Todas las personas',
    ],
];
