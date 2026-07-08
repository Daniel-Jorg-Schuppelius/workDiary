<?php

/*
 * Données météo pour le journal de chantier (Feature 062, MVP-131).
 */

return [
    'block' => ['title' => 'Météo'],
    'temp' => 'Température',
    'precipitation' => 'Précipitations',
    'wind' => 'Rafale de vent',
    'source' => 'Source',
    'fetched' => 'Récupéré',
    'none' => 'Aucune donnée météo.',
    'attach' => [
        'button' => 'Récupérer la météo',
        'success' => 'Météo du jour ajoutée.',
        'unavailable' => 'Aucune donnée météo disponible (pas de lieu/coordonnées ou service injoignable) — à compléter ultérieurement.',
    ],
];
