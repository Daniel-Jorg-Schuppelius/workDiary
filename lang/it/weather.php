<?php

/*
 * Dati meteo per il giornale di cantiere (Feature 062, MVP-131).
 */

return [
    'block' => ['title' => 'Meteo'],
    'temp' => 'Temperatura',
    'precipitation' => 'Precipitazioni',
    'wind' => 'Raffica di vento',
    'source' => 'Fonte',
    'fetched' => 'Recuperato',
    'none' => 'Nessun dato meteo.',
    'providers' => [
        // Weather service display names (proper nouns; DWD requires CC-BY attribution).
        'open-meteo' => 'Open-Meteo',
        'dwd' => 'Deutscher Wetterdienst (DWD)',
    ],
    'attach' => [
        'button' => 'Recupera meteo',
        'success' => 'Meteo del giorno allegato.',
        'unavailable' => 'Nessun dato meteo disponibile (nessun luogo/coordinate o servizio non raggiungibile) — recuperabile in seguito.',
    ],
];
