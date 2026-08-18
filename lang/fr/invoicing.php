<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoicing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'service' => 'Prestation',
    'service_on' => 'Prestation le :date',
    'hourly_rate' => 'Taux horaire',
    'unit_hour' => 'h',
    'unit_flat' => 'forfait',
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
        'gaeb' => [
            'button' => 'GAEB (X89)',
            'button_title' => 'Télécharger la facture au format GAEB pour les maîtres d’ouvrage',
        ],
        'zugferd' => [
            'button' => 'ZUGFeRD (PDF)',
            'button_title' => 'Télécharger le PDF ZUGFeRD (PDF/A-3, EN 16931)',
            'error_intro' => 'Le PDF ZUGFeRD ne peut pas être généré :',
            'unavailable' => 'La génération de PDF ZUGFeRD n\'est pas disponible sur ce système (php-pdf-toolkit manquant).',
            'failed' => 'La génération du PDF ZUGFeRD a échoué.',
        ],
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

    // Aperçu de la facture dans le dialogue de création (MVP-462).
    'source_times' => 'Afficher :count saisie de temps source|Afficher :count saisies de temps sources',
    'preview' => [
        'heading' => 'Aperçu :',
        'empty' => 'Aucun temps facturable ni frais de déplacement pour les filtres sélectionnés.',
        'entry_count' => ':count saisie|:count saisies',
        'travel' => '+ :count déplacement(s)',
        'warning_late' => ':count saisie tardive : la date de prestation tombe dans une période déjà facturée.|:count saisies tardives : les dates de prestation tombent dans des périodes déjà facturées.',
        'column' => [
            'description' => 'Position',
            'duration' => 'Durée',
            'rate' => 'Taux',
            'amount' => 'Montant',
        ],
        'entries_heading' => 'Afficher/exclure des saisies individuelles',
        'exclude' => 'exclure',
        'exclude_hint' => 'Les saisies exclues restent ouvertes et réapparaissent au prochain cycle de facturation.',
    ],
];
