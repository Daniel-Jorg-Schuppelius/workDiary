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
    // Online-Vorauszahlung der Direktbuchung (MVP-1515).
    'prepayment' => [
        'hint' => 'Une réservation directe exige un acompte en ligne de :percent % du prix TTC. La réservation est maintenue :minutes minutes ; elle est confirmée à réception du paiement.',
        'amount' => 'Acompte :amount',
        'due' => 'Paiement avant :until',
        'pay_now' => 'Payer maintenant',
        'unavailable' => 'La réservation directe n’est pas possible pour le moment, car aucun paiement en ligne n’est configuré. Veuillez envoyer une demande.',
        'not_due' => 'Aucun paiement n’est en attente pour cette réservation.',
        'expired_error' => 'Le délai de paiement est dépassé ; la réservation a été libérée.',
        'checkout_failed' => 'La page de paiement est momentanément indisponible. Votre réservation est maintenue jusqu’à :until — veuillez réessayer via « Payer maintenant ».',
        'description' => 'Acompte :subject à partir du :from',
        'confirmed' => 'Paiement reçu — votre réservation est confirmée.',
        'pending' => 'Le paiement n’est pas encore confirmé. Dès que le prestataire de paiement le signale, votre réservation est confirmée.',
        'late_notification' => 'Acompte reçu après le délai de :customer',
        'late_message' => ':subject : la réservation avait déjà expiré. Veuillez réserver à nouveau ou rembourser le paiement.',
        'expired_reason' => 'Acompte en ligne non reçu dans le délai.',
        'withdrawn_reason' => 'Réservation directe impayée retirée par le client.',
        'on_case' => 'Acompte en ligne :amount payé — imputé sur la facture.',
        'profile_percent' => 'Acompte en réservation directe (%)',
        'profile_percent_hint' => 'Vide = valeur de l’organisation, 0 = pas d’acompte pour cet objet de location.',
        'badge' => 'Acompte :percent %',
        'hint_list' => 'Une réservation directe exige l’acompte en ligne indiqué dans la liste. La réservation est maintenue :minutes minutes ; elle est confirmée à réception du paiement.',
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
