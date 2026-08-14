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
];
