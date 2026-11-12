<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'priority' => [
        'low' => 'Basse',
        'normal' => 'Normale',
        'high' => 'Haute',
        'urgent' => 'Urgente',
    ],
    'location_mode' => [
        'onsite' => 'Sur site',
        'remote' => 'À distance',
        'hybrid' => 'Hybride',
    ],
    'mode' => [
        'fixed' => 'Planifié',
        'deadline' => 'Échéance',
        'window' => 'Fenêtre',
        'recurring' => 'Récurrent',
        'backlog' => 'Backlog',
    ],
    'status' => [
        'Planned' => 'Planifié',
        'Accepted' => 'Acceptée',
        'InProgress' => 'En cours',
        'WaitingCustomer' => 'En attente de réponse',
        'WaitingMaterial' => 'En attente de matériel',
        'Completed' => 'Terminé',
        'AcceptedFinal' => 'Réceptionné',
        'Invoiced' => 'Facturée',
        'Cancelled' => 'Annulée',
    ],
    'planned_duration' => [
        'label' => 'Durée prévue (HH:MM)',
        'hint' => 'Vide : durée du créneau ou du rendez-vous. Sert de plan dans Plan/réel, Analyse des types de commande et Capacité du personnel.',
        'format' => 'Veuillez saisir la durée prévue en heures:minutes, par ex. 1:30.',
        'range' => 'La durée prévue doit être comprise entre 0:01 et 168:00.',
    ],
    // Kennzahl Plan/Ist am Auftrag (MVP-1552).
    'plan_actual' => [
        'label' => 'Prévu/réel',
        'no_plan' => 'sans prévision',
        'own_only' => 'vos temps uniquement',
    ],
    // Auftrags-Lebenszyklus (MVP-1596).
    'lifecycle' => [
        'action' => [
            'reject' => 'Rendre la prise en charge',
            'revert_invoiced' => 'Annuler la facturation',
        ],
        'reject' => [
            'title' => 'Rendre la prise en charge',
            'legend' => 'Restitution',
            'hint' => 'L\'ordre repasse à « Planifié » et peut être pris en charge par une autre personne. Le motif figure dans l\'historique.',
        ],
        'handover' => [
            'legend' => 'Réception',
            'accepted_by' => 'Réceptionné par (nom chez le client)',
            'hint' => 'Le procès-verbal ne porte pas de signature du client. Veuillez indiquer la personne qui a réceptionné les travaux chez le client.',
        ],
        'accepted_final_by' => 'Réceptionné par :name le :date',
        'invoiced_with' => 'Facturé avec :reference',
        'invoiced' => [
            'transfer_reference' => 'Transfert n° :id',
        ],
        'cancel' => [
            'run_abort_reason' => 'Ordre annulé : :reason',
        ],
        'error' => [
            'stopwatch_running' => 'L\'ordre ne peut être clôturé que lorsqu\'aucun chronomètre ne tourne. Encore actif : :names.',
        ],
    ],
];
