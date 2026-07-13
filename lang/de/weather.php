<?php

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
];
