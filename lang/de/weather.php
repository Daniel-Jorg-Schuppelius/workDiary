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
 * Wetterdaten fürs Bautagebuch (Feature 062, MVP-131).
 */

return [
    'block' => ['title' => 'Wetter'],
    'temp' => 'Temperatur',
    'precipitation' => 'Niederschlag',
    'wind' => 'Windspitze',
    'source' => 'Quelle',
    'fetched' => 'Abgerufen',
    'none' => 'Keine Wetterdaten hinterlegt.',
    'providers' => [
        // Anzeigename der Wetterdienste (Eigennamen, CC-BY-Attribution beim DWD).
        'open-meteo' => 'Open-Meteo',
        'dwd' => 'Deutscher Wetterdienst (DWD)',
    ],
    'attach' => [
        'button' => 'Wetter abrufen',
        'success' => 'Wetterdaten des Tages angehängt.',
        'unavailable' => 'Keine Wetterdaten verfügbar (kein Ort/Koordinaten oder Dienst nicht erreichbar) — später nachholbar.',
    ],
    // Warnschwellen der Vorhersage (Feature 062, MVP-716).
    'warning' => [
        'threshold' => [
            'rain' => 'Starkregen',
            'gust' => 'Sturmböen',
            'frost' => 'Frost',
            'heat' => 'Hitze',
        ],
    ],
];
