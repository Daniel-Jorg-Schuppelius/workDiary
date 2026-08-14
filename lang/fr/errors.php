<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : errors.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'csv' => [
        'unreadable' => 'Le fichier n\'est pas lisible.',
        'header_missing' => 'Ligne d\'en-tête manquante ou illisible : :error',
        'name_column_missing' => 'Colonne requise « Name » introuvable.',
    ],
    'routing' => [
        'nominatim_missing_coords' => 'La réponse de Nominatim ne contient pas de coordonnées.',
        'nominatim_http' => 'Nominatim a renvoyé HTTP :status.',
    ],
    'upload' => [
        'too_large' => 'Le fichier est trop volumineux (max. :max Ko).',
        'type_not_allowed' => 'Type de fichier non autorisé.',
    ],

    // Pages d'erreur HTTP (041-P0, MVP-053)
    'request_id' => 'ID de la requête',
    'report_problem' => 'Signaler un problème',
    '404' => [
        'title' => 'Page introuvable',
        'message' => "La page demandée n'existe pas ou a été déplacée.",
    ],
    '403' => [
        'title' => 'Accès refusé',
        'message' => "Vous n'avez pas l'autorisation pour cette action. Veuillez contacter votre administration.",
    ],
    '419' => [
        'title' => 'Session expirée',
        'message' => 'La page est restée ouverte trop longtemps. Veuillez la recharger et réessayer.',
    ],
    '500' => [
        'title' => 'Erreur interne',
        'message' => "Une erreur inattendue s'est produite. Veuillez réessayer plus tard ou signaler le problème avec l'ID de la requête.",
    ],
];
