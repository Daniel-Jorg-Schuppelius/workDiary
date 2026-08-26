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
    // Warnschwellen der Vorhersage (Feature 062, MVP-716).
    'warning' => [
        'threshold' => [
            'rain' => 'Pioggia intensa',
            'gust' => 'Raffiche di tempesta',
            'frost' => 'Gelo',
            'heat' => 'Caldo',
        ],
    ],
];
