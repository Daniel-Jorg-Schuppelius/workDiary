<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : rental.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'terms' => [
        'title' => 'Conditions de location',
        'signed' => 'Contrat :contract, version :revision, signé le :date',
        'missing' => 'Aucune condition de location signée pour ce client.',
        'missing_required' => 'Aucune condition de location signée — la remise n’est possible qu’ensuite.',
        'create_agreement' => 'Créer les conditions de location',
        'required' => 'La remise nécessite des conditions de location signées par le client (paramètre de l’organisation).',
    ],
    // Direktbuchung und Preisangabe im Portal (MVP-916).
    'portal' => [
        'price' => 'Prix (HT)',
        'price_estimate' => 'env. :amount',
        'direct_intro' => 'Vous réservez directement les appareils disponibles ; vous pouvez aussi demander un appareil ou un groupe d\'appareils, et nous confirmons. Le prix provient de la liste de prix et s\'entend hors taxes.',
        'direct_book' => 'Réserver directement',
        'direct_hint' => 'Réserve immédiatement l\'appareil choisi s\'il est disponible sur la période.',
        'direct_booked' => 'Appareil réservé — nous vous enverrons les documents de remise.',
        'direct_status' => 'Réservé directement',
        'direct_disabled' => 'La réservation directe n\'est pas activée.',
        'direct_case_note' => 'Réservation directe depuis le portail client.',
        'direct_notification' => 'Réservation directe de :customer',
    ],
    // Mietpreisregeln (MVP-950).
    'rule' => [
        'title' => 'Règles de prix de location',
        'empty' => 'Aucune règle : le tarif journalier s’applique.',
        'add' => 'Ajouter une règle',
        'line' => ':label (:percent %)',
        'from_utilization' => 'à partir de :percent % d’occupation',
        'kind' => [
            'season' => 'Saison',
            'weekday' => 'Jours de la semaine',
            'utilization' => 'Occupation',
        ],
        'field' => [
            'kind' => 'Type',
            'label' => 'Libellé',
            'valid_from' => 'Valable à partir du',
            'valid_until' => 'Valable jusqu’au',
            'weekdays' => 'Jours de la semaine',
            'utilization_min_percent' => 'À partir d’une occupation de (%)',
            'adjust_percent' => 'Majoration/remise (%)',
        ],
        'weekday' => [
            '1' => 'lun.',
            '2' => 'mar.',
            '3' => 'mer.',
            '4' => 'jeu.',
            '5' => 'ven.',
            '6' => 'sam.',
            '7' => 'dim.',
        ],
        'flash' => [
            'saved' => 'Règle de prix enregistrée.',
            'deleted' => 'Règle de prix supprimée.',
        ],
    ],
];
