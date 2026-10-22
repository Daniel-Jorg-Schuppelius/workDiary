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
        'title' => 'Informe de rondas',
        'subtitle' => 'Rondas por ruta y objeto en el periodo: previstas, puntuales, con retraso y omitidas.',
        'expected' => 'Puntos previstos',
        'onTime' => 'puntual',
        'late' => 'con retraso',
        'missed' => 'omitido',
        'note' => 'Motivo',
        'runs' => 'Rondas',
        'punctuality' => 'Puntualidad',
        'aborted' => 'interrumpidas',
        'allRoutes' => 'Todas las rutas',
        'allSites' => 'Todos los objetos',
        'empty' => 'No hay rondas en el periodo.',
        'summary' => ':runs rondas, :completed completadas y :aborted interrumpidas',
    ],
    'scan' => [
        'implausibleTime' => 'La hora de escaneo del dispositivo está fuera de la ronda; compruebe el reloj del dispositivo.',
    ],
];
