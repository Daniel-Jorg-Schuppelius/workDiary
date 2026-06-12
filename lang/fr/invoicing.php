<?php

return [
    'service' => 'Prestation',
    'service_on' => 'Prestation le :date',
    'hourly_rate' => 'Taux horaire',
    'unit_hour' => 'h',
    'unit_piece' => 'pce',
    'tax_rate' => 'Taux de TVA',
    'currency' => 'Devise',
    'totals' => [
        'net' => 'Net',
        'tax' => 'TVA',
        'gross' => 'Brut',
    ],

    // Facturation électronique (fonctionnalité 045, section 8) : XRechnung (UBL 2.1, EN 16931).
    'buyer_reference' => 'Leitweg-ID / référence acheteur (BT-10)',
    'buyer_reference_hint' => 'Obligatoire pour la XRechnung (facture électronique) : le Leitweg-ID pour les administrations, sinon une référence fournie par le client.',
    'einvoice' => [
        'button' => 'XRechnung',
        'button_title' => 'Télécharger la XRechnung (UBL 2.1, EN 16931)',
        'error_intro' => 'La XRechnung ne peut pas être générée :',
        'payment_terms' => 'Payable sous :days jours sans escompte.',
        'exemption_small_business' => 'Pas de TVA facturée conformément au § 19 UStG (régime allemand des petites entreprises).',
        'error' => [
            'status' => 'La facture doit être émise ou payée.',
            'no_items' => 'La facture ne contient aucune ligne.',
            'missing_buyer_reference' => 'Le Leitweg-ID/la référence acheteur (BT-10) manque chez le client.',
            'missing_seller_field' => 'Donnée vendeur manquante : :field (paramètres de l\'organisation → facturation).',
            'missing_tax_id' => 'Ni numéro de TVA intracommunautaire ni numéro fiscal configuré dans les paramètres de l\'organisation.',
            'missing_iban' => 'L\'IBAN pour le virement SEPA manque dans les paramètres de l\'organisation.',
            'missing_tax_rate' => 'La facture ne porte aucun taux de taxe.',
            'totals_mismatch' => 'Les totaux de la facture sont incohérents (lignes, sous-total, taxe, total).',
        ],
        'warning' => [
            'missing_seller_contact' => 'Contact vendeur incomplet (nom, téléphone, e-mail) — la XRechnung exige des coordonnées complètes (BR-DE-2).',
            'missing_bic' => 'Le BIC manque (recommandé pour les virements SEPA).',
            'buyer_address_incomplete' => 'Adresse du client incomplète (rue/code postal/ville).',
            'missing_buyer_email' => 'L\'e-mail du client manque (adresse électronique de réception BT-49).',
            'missing_due_date' => 'Date d\'échéance manquante — le délai de paiement par défaut est utilisé.',
        ],
    ],
];
