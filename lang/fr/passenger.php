<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : passenger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Transport de personnes (MVP-456, profil taxi/voiture de location).
return [
    'entry_title' => 'Ordre de course (:mode)',
    'entry_content' => 'Transport de personnes — canal de commande : :channel. Détails dans le dossier de course.',

    'error' => [
        'destination_required' => 'Indiquer une destination ou confirmer explicitement une destination libre.',
        'receipt_channel_invalid' => 'La voiture de location / le transport à la demande exige une réception d\'ordre prouvable au siège — héler est interdit (§ 49 al. 4 PBefG).',
        'pickup_required' => 'Le lieu de prise en charge est obligatoire.',
        'not_assigned' => 'Une course ne démarre qu\'après répartition (chauffeur, véhicule, concession).',
        'tariff_required' => 'Le taxi roule au tarif réglementé — veuillez choisir un tarif.',
        'fixed_price_outside_corridor' => 'Le prix fixe est en dehors du corridor officiellement autorisé.',
        'meter_value_required' => 'La valeur du taximètre/appareil est obligatoire à la clôture.',
        'tax_decision_required' => 'La décision fiscale (taux) est obligatoire à la clôture.',
        'payment_required' => 'Le mode de paiement est obligatoire à la clôture.',
        'invalid_transition' => 'Changement de statut non autorisé.',
        'invalid_transition_detail' => 'Changement de statut non autorisé : :from → :to.',
        'return_not_applicable' => 'La preuve de retour ne concerne que la voiture de location.',
    ],

    'issue' => [
        'driver_unqualified' => 'Autorisation de transport de personnes absente ou expirée.',
        'concession_missing' => 'Aucune concession valable pour ce mode d\'exploitation.',
        'vehicle_profile_missing' => 'Le véhicule n\'a pas de profil de transport de personnes.',
        'vehicle_mode_unsupported' => 'Le véhicule n\'est pas autorisé pour ce mode d\'exploitation.',
        'vehicle_proofs_expired' => 'Justificatifs du véhicule expirés (étalonnage/BOKraft/contrôle).',
        'vehicle_not_barrier_free' => 'La course exige l\'accessibilité — le véhicule ne l\'offre pas.',
        'vehicle_no_wheelchair_place' => 'La course exige une place fauteuil roulant — le véhicule n\'en a pas.',
        'vehicle_too_small' => 'Le nombre de passagers dépasse les places du véhicule.',
    ],

    'proof' => [
        'meter_calibration' => 'Étalonnage taximètre/compteur',
        'bokraft' => 'Contrôle BOKraft',
        'hu' => 'Contrôle technique',
    ],
];
