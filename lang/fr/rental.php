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
        'credited_on_case' => 'Acompte en ligne de :amount transmis au logiciel de comptabilité avec les positions de location — imputez-le sur la facture dans ce logiciel.',
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
    // Vormerkhinweis, Protokollpositionen, Mindestzustand, Reinigung (MVP-1604).
    'condition' => [
        'below_minimum' => ':asset est dans l’état « :current », inférieur à l’état minimal « :minimum » du profil de location, et ne peut pas être attribué.',
        'below_badge' => 'sous l’état minimal',
    ],
    'soft' => [
        'title' => 'Des préréservations existent pour ces appareils sur cette période',
        'hint' => 'Les préréservations ne bloquent pas. Veuillez clarifier avec les dossiers indiqués qui reçoit l’appareil.',
        'reserved_warning' => 'Il existe :count préréservation d’un autre dossier sur cette période. Les dossiers concernés figurent dans le dossier.|Il existe :count préréservations d’autres dossiers sur cette période. Les dossiers concernés figurent dans le dossier.',
    ],
    'cleaning' => [
        'not_open' => 'Cette plage n’est pas une plage de nettoyage ouverte.',
        'done' => 'Nettoyage terminé — l’appareil peut de nouveau être attribué.',
        'mark_done' => 'Nettoyage terminé',
        'form_hint' => 'Crée une plage de nettoyage ; l’appareil ne peut pas être attribué avant la fin du nettoyage.',
    ],
    'report' => [
        'checklist' => 'Liste de contrôle',
        'accessories' => 'Accessoires',
        'quantity_of' => 'Quantité :label',
        'accessories_hint' => 'Décochez une pièce manquante.',
        'accessory_missing' => 'manquant',
        'condition_items' => 'Points d’état',
        'item_label' => 'Pièce ou point de contrôle',
        'item_label_n' => 'Point d’état :n : désignation',
        'item_state_n' => 'Point d’état :n : constat',
        'item_note' => 'Remarque',
        'item_note_n' => 'Point d’état :n : remarque',
        'rows_hint' => 'Les lignes vides ne sont pas enregistrées.',
        'consumables' => 'Consommation',
        'consumable_label' => 'Consommable',
        'consumable_label_n' => 'Consommation :n : désignation',
        'consumable_quantity_n' => 'Consommation :n : quantité',
        'consumable_unit_n' => 'Consommation :n : unité',
        'details' => 'Afficher les points',
        'point_done' => 'fait',
        'point_open' => 'ouvert',
        'returns' => 'Reprises',
    ],
    'profile' => [
        'home_site' => 'Site d’attache',
        'min_condition' => 'État minimal',
        'min_condition_hint' => 'Si l’état actuel est inférieur, l’appareil ne peut pas être attribué.',
        'no_minimum' => 'aucun état minimal',
        'current_condition' => 'État actuel',
        'current_condition_hint' => 'Repris du procès-verbal à chaque reprise ; à ajuster ici après une remise en état.',
        'condition_unknown' => 'non renseigné',
        'checklist' => 'Liste de contrôle pour la remise et la reprise (un point par ligne)',
        'checklist_hint' => 'Les points apparaissent à cocher dans les procès-verbaux de remise et de reprise.',
    ],
    'deposit' => [
        'waive' => 'Remettre',
        'waive_reason' => 'Motif de la remise',
        'flash' => [
            'waived' => 'Caution remise.',
        ],
        'error' => [
            'not_waivable' => 'Seules les cautions demandées et non encore reçues peuvent être remises.',
            'reason_required' => 'La remise d\'une caution exige un motif.',
        ],
    ],
];
