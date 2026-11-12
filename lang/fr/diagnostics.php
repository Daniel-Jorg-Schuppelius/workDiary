<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diagnostics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Diagnose-Seite: Überwachungszugang (MVP-1618).
return [
    'token' => [
        'heading' => 'Accès de supervision',
        'intro' => 'Une supervision externe récupère le rapport sans connexion à l’adresse :url et envoie le jeton dans l’en-tête « Authorization: Bearer … ». Les noms et adresses n’y figurent pas.',
        'create' => 'Créer un accès',
        'name' => 'Désignation',
        'name_help' => 'Par exemple le nom du système de supervision, afin de pouvoir identifier l’accès plus tard.',
        'hint' => 'Identifiant',
        'created_by' => 'Créé',
        'last_used' => 'Dernier appel',
        'status' => 'Statut',
        'active' => 'Actif',
        'revoked_state' => 'Révoqué',
        'revoke' => 'Révoquer',
        'revoke_confirm' => 'Révoquer cet accès ? La supervision ne recevra plus le rapport.',
        'shown_once' => 'Copiez le jeton maintenant. Il n’est affiché qu’une seule fois et ne peut pas être récupéré.',
        'empty' => 'Aucun accès de supervision créé pour le moment.',
        'created' => 'Accès de supervision créé.',
        'revoked' => 'Accès de supervision révoqué.',
    ],
];
