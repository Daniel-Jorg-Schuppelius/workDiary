<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : maintenance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'window' => [
        'title' => 'Fenêtres de maintenance',
        'subtitle' => 'Annoncer, démarrer, prolonger et clôturer les interruptions planifiées de façon traçable.',
        'read_only_message' => 'Maintenance : l\'application est temporairement en lecture seule.',
        'scope' => [
            'system' => 'À l\'échelle de l\'installation',
            'organization' => 'Cette organisation uniquement',
        ],
        'mode' => [
            'full' => 'Blocage complet',
            'read_only' => 'Lecture seule',
            'block_ingest' => 'Ingestion bloquée',
            'read_only_toggle' => 'Mode lecture seule (la consultation reste possible)',
            'block_ingest_toggle' => 'Bloquer l\'ingestion terminal/CTI/localisation pendant la maintenance',
        ],
        'status' => [
            'planned' => 'Planifiée',
            'announced' => 'Annoncée',
            'active' => 'Active',
            'extended' => 'Prolongée',
            'completed' => 'Terminée',
            'rolled_back' => 'Rollback',
            'cancelled' => 'Annulée',
        ],
        'field' => [
            'window' => 'Créneau',
            'scope' => 'Portée',
            'mode' => 'Mode',
            'status' => 'Statut',
            'actions' => 'Actions',
            'announce_from' => 'Annonce à partir de',
            'starts_at' => 'Début',
            'ends_at' => 'Fin',
            'message' => 'Texte d\'information',
        ],
        'action' => [
            'plan' => 'Planifier une fenêtre de maintenance',
            'save' => 'Planifier',
            'announce' => 'Annoncer',
            'start' => 'Démarrer maintenant',
            'complete' => 'Terminer',
            'extend' => 'Prolonger',
            'rollback' => 'Rollback',
            'cancel' => 'Annuler',
        ],
        'banner' => [
            'upcoming' => 'Maintenance planifiée : :from à :to — veuillez enregistrer votre travail à temps.',
            'read_only' => 'Maintenance active jusqu\'à :to — les modifications sont temporairement impossibles.',
        ],
        'hint' => [
            'message' => 'Facultatif : que maintient-on, à quoi s\'attendre ?',
        ],
        'empty' => [
            'title' => 'Aucune fenêtre de maintenance',
            'message' => 'Aucune fenêtre de maintenance planifiée.',
        ],
        'flash' => [
            'planned' => 'Fenêtre de maintenance planifiée.',
            'announce' => 'Fenêtre de maintenance annoncée.',
            'start' => 'Fenêtre de maintenance démarrée.',
            'complete' => 'Fenêtre de maintenance terminée.',
            'extend' => 'Fenêtre de maintenance prolongée.',
            'rollback' => 'Maintenance clôturée en rollback.',
            'cancel' => 'Fenêtre de maintenance annulée.',
        ],
    ],
];
