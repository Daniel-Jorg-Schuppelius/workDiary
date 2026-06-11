<?php
/*
 * Chaînes pour le module de lanceurs d'alerte (catégories, etc.).
 */

return [
    'category' => [
        'corruption' => 'Corruption et pots-de-vin',
        'fraud' => 'Fraude, abus de confiance et vol',
        'money_laundering' => 'Blanchiment d\'argent et financement du terrorisme',
        'procurement' => 'Infractions aux marchés publics et à la concurrence',
        'data_protection' => 'Protection des données et sécurité de l\'information',
        'product_safety' => 'Sécurité des produits et protection des consommateurs',
        'environment' => 'Infractions à l\'environnement et à la sécurité au travail',
        'discrimination' => 'Discrimination, harcèlement et abus de pouvoir',
        'policy_violation' => 'Violation des directives internes',
        'other' => 'Autre infraction légale potentielle',
    ],
    'status' => [
        'submitted' => 'Déposé',
        'acknowledged' => 'Réception confirmée',
        'triage' => 'Examen préliminaire',
        'investigating' => 'En cours de traitement',
        'waiting_reporter' => 'En attente du lanceur d\'alerte',
        'referred' => 'Transmis',
        'closed_substantiated' => 'Clôturé – fondé',
        'closed_unsubstantiated' => 'Clôturé – non fondé',
        'closed_out_of_scope' => 'Clôturé – hors champ d\'application',
        'closed_duplicate' => 'Clôturé – doublon',
        'retention_review' => 'Examen de conservation',
        'legal_hold' => 'Suspension de suppression (legal hold)',
        'deleted' => 'Supprimé',
    ],
    'reporter_status' => [
        'received' => 'Reçu et en cours d\'examen',
        'in_progress' => 'En cours de traitement',
        'awaiting_you' => 'Votre retour est attendu',
        'closed' => 'Clôturé',
    ],
];
