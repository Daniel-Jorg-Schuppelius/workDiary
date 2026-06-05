<?php

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
