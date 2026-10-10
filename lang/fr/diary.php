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
];
