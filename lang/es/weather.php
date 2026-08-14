<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : weather.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
