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
    'attach' => [
        'button' => 'Wetter abrufen',
        'success' => 'Wetterdaten des Tages angehängt.',
        'unavailable' => 'Keine Wetterdaten verfügbar (kein Ort/Koordinaten oder Dienst nicht erreichbar) — später nachholbar.',
    ],
];
