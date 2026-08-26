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
    'providers' => [
        // Weather service display names (proper nouns; DWD requires CC-BY attribution).
        'open-meteo' => 'Open-Meteo',
        'dwd' => 'Deutscher Wetterdienst (DWD)',
    ],
    'attach' => [
        'button' => 'Récupérer la météo',
        'success' => 'Météo du jour ajoutée.',
        'unavailable' => 'Aucune donnée météo disponible (pas de lieu/coordonnées ou service injoignable) — à compléter ultérieurement.',
    ],
    // Warnschwellen der Vorhersage (Feature 062, MVP-716).
    'warning' => [
        'threshold' => [
            'rain' => 'Fortes pluies',
            'gust' => 'Rafales de tempête',
            'frost' => 'Gel',
            'heat' => 'Chaleur',
        ],
    ],
];
