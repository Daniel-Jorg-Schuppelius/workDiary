<?php

/*
 * Datos meteorológicos para el diario de obra (Feature 062, MVP-131).
 */

return [
    'block' => ['title' => 'Meteorología'],
    'temp' => 'Temperatura',
    'precipitation' => 'Precipitación',
    'wind' => 'Racha de viento',
    'source' => 'Fuente',
    'fetched' => 'Obtenido',
    'none' => 'Sin datos meteorológicos.',
    'providers' => [
        // Weather service display names (proper nouns; DWD requires CC-BY attribution).
        'open-meteo' => 'Open-Meteo',
        'dwd' => 'Deutscher Wetterdienst (DWD)',
    ],
    'attach' => [
        'button' => 'Obtener meteorología',
        'success' => 'Datos meteorológicos del día adjuntados.',
        'unavailable' => 'No hay datos meteorológicos (sin ubicación/coordenadas o servicio no disponible) — se puede añadir más tarde.',
    ],
];
