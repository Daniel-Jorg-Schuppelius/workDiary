<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dispatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'heading' => 'Répartition',
    'badge_prefix' => 'Répartition',
    'set_status' => ':status',
    'override_reason' => 'Motif du forçage',
    'override_placeholder' => 'Pourquoi confirmer malgré le conflit ?',
    'conflicts' => [
        'hard' => 'Conflits bloquants',
        'soft' => 'Avertissements',
        'none' => 'Aucun conflit pour cette affectation.',
    ],
    'vehicle' => [
        'heading' => 'Réservation de véhicule',
        'label' => 'Véhicule',
        'from' => 'Du',
        'to' => 'Au',
        'reserve' => 'Réserver',
        'release' => 'Libérer',
        'none' => 'Aucune réservation de véhicule pour cet ordre.',
    ],
    'reservations' => [
        'title' => 'Réservations de véhicules',
        'subtitle' => 'Gérer les réservations par véhicule.',
        'all_vehicles' => 'Tous les véhicules',
        'reserved_by' => 'Réservé par',
        'empty' => 'Aucune réservation disponible.',
    ],
];
