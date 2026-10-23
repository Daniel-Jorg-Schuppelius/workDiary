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
    'title' => 'Attività',
    'subtitle' => 'Chi ha modificato cosa e quando · :range',
    'subject_title' => 'Cronologia delle modifiche',
    'more' => 'Le voci meno recenti si trovano nel registro di audit.',
    'more_in_audit' => 'Voci meno recenti nel registro di audit',
    'newer' => 'Più recenti',
    'older' => 'Meno recenti',
    'filter' => [
        'type' => 'Tipo di oggetto',
        'all_types' => 'Tutti i tipi di oggetto',
        'user' => 'Persona',
        'all_users' => 'Tutte le persone',
    ],
];
