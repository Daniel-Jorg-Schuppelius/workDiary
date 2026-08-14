<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : work_schedule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'type' => [
        'flextime' => 'Horario flexible',
        'weekly' => 'Horario semanal fijo',
        'per_weekday' => 'Por día de la semana',
        'trust' => 'Horario de confianza',
    ],
    'type_hint' => [
        'flextime' => 'Objetivo diario uniforme en los días laborables, con franjas central y de referencia.',
        'weekly' => 'Un único objetivo semanal, distribuible libremente a lo largo de la semana.',
        'per_weekday' => 'Horas individuales u horarios fijos de inicio–fin por día.',
        'trust' => 'Sin seguimiento de objetivos: solo se registra la asistencia real.',
    ],
];
