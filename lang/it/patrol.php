<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : patrol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Wachrundgänge (Phase 156).
return [
    'report' => [
        'title' => 'Rapporto ronde',
        'subtitle' => 'Ronde per percorso e sito nel periodo: previste, puntuali, in ritardo e mancate.',
        'expected' => 'Punti previsti',
        'onTime' => 'puntuale',
        'late' => 'in ritardo',
        'missed' => 'mancato',
        'note' => 'Motivazione',
        'runs' => 'Ronde',
        'punctuality' => 'Puntualità',
        'aborted' => 'interrotte',
        'allRoutes' => 'Tutti i percorsi',
        'allSites' => 'Tutti i siti',
        'empty' => 'Nessuna ronda nel periodo.',
        'summary' => ':runs ronde, di cui :completed completate e :aborted interrotte',
    ],
    'scan' => [
        'implausibleTime' => 'L’orario di scansione del dispositivo è fuori dalla ronda: controllare l’orologio del dispositivo.',
    ],
];
