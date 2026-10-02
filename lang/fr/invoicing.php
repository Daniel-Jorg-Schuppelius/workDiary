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
    // Girocode/EPC-QR auf dem Rechnungs-PDF (Feature 111, MVP-600).
    'girocode' => [
        'alt' => 'QR-code de paiement',
        'hint' => 'À scanner avec l’application bancaire',
    ],
    // Belegkette (MVP-1057).
    'chain' => [
        'title' => 'À facturer et à relancer',
        'description' => 'Ce qui reste en suspens entre devis, prestation et facture — dans tous les modules.',
        'open' => 'Ouvrir',
        'empty_title' => 'Rien en attente',
        'empty' => 'Tous les devis acceptés sont facturés et aucune relance n’est due.',
        'nothing_here' => 'Rien en attente.',
        'more' => '… et :count de plus.',
        'quotes_to_invoice' => 'Devis acceptés sans facture',
        'quotes_follow_up' => 'Devis à relancer',
        'invoices_overdue' => 'Factures en retard',
        'unbilled_time' => 'Temps facturable par client',
        'boq_progress' => 'Avancement supérieur aux acomptes (bordereau)',
        'accepted_on' => 'accepté le :date',
        'expired_on' => 'validité expirée le :date',
        'follow_up_on' => 'relance le :date',
        'due_on' => 'échue depuis le :date',
        'no_customer' => 'sans client',
        'unbilled_detail' => ':entries saisies · :duration',
        'boq_detail' => 'Avancement :progress %',
        'col' => [
            'document' => 'Document',
            'status' => 'État',
            'amount' => 'Montant (HT)',
        ],
    ],
    // Gliederung von Angebot und Rechnung (MVP-1054).
    'line_kind' => [
        'item' => 'Ligne',
        'title' => 'Titre',
        'text' => 'Texte',
        'alternative' => 'Variante',
        'subtotal' => 'Total du titre :number :title',
        'add_title' => 'Ajouter un titre',
        'add_text' => 'Ajouter un texte',
        'add_alternative' => 'Ajouter une variante',
        'alternative_marker' => 'Ligne en variante — comptée uniquement si vous la choisissez',
        'alternative_hint' => 'Ligne en variante : elle n’entre pas dans le total du devis tant que le client ne la choisit pas à la place d’une autre ligne.',
        'position_hint' => 'Détermine l’ordre ; les titres numérotent les lignes qui suivent.',
    ],
    // Arbeitskosten nach § 35a EStG (MVP-1053).
    'labour_costs' => [
        'disclosure' => [
            'off' => 'Ne jamais indiquer',
            'private_customers' => 'Indiquer pour les particuliers (sans société ni n° de TVA)',
            'always' => 'Toujours indiquer',
        ],
        'share' => 'Part de main-d’œuvre § 35a EStG (%)',
        'share_hint' => 'Part des frais de main-d’œuvre, de machines et de déplacement dans cette ligne. Le matériel ne compte pas. Vide = non déterminé.',
        'override' => 'Indiquer les frais de main-d’œuvre selon le § 35a EStG',
        'override_hint' => 'S’applique uniquement à ce document ; sans choix, le paramètre de l’organisation décide.',
        'override_default' => 'Selon le paramètre de l’organisation (:rule)',
        'override_on' => 'Indiquer',
        'override_off' => 'Ne pas indiquer',
        'pdf_line' => 'Frais de main-d’œuvre, de machines et de déplacement inclus dans le montant de la facture (§ 35a EStG) : :gross :currency, dont :tax :currency de TVA.',
        'pdf_line_final' => 'Frais de main-d’œuvre, de machines et de déplacement inclus dans la prestation totale (§ 35a EStG), factures d’acompte comprises : :gross :currency, dont :tax :currency de TVA.',
        'pdf_line_quote' => 'Frais de main-d’œuvre, de machines et de déplacement prévus dans le montant du devis (§ 35a EStG) : :gross :currency, dont :tax :currency de TVA.',
        'einvoice_note' => 'Frais de main-d’œuvre, de machines et de déplacement inclus selon le § 35a EStG : :gross :currency TTC, dont :tax :currency de TVA.',
        'undetermined' => ':count ligne sans part de main-d’œuvre déterminée|:count lignes sans part de main-d’œuvre déterminée',
    ],
    // Sicherheitseinbehalte § 17 VOB/B (Feature 113, MVP-602).
    'retention' => [
        'final_only' => 'Les factures d\'acompte et partielles ne portent pas de retenue de garantie — elle est calculée sur la prestation totale dans la facture finale.',
        'final_base_hint' => 'Facture finale : le pourcentage porte sur la prestation totale avant déduction des acomptes ; il est déduit du montant à payer.',
        'exceeds_after_settlement' => 'Les retenues saisies dépassent le montant à payer après imputation des acomptes. Veuillez ajuster les retenues.',
        'dialog_title' => 'Enregistrer une retenue',
        'submit' => 'Enregistrer',
        'dialog_hint' => 'La retenue figure sur le document et est déduite du poste ouvert. Elle n’est plus modifiable après émission.',
        'kind' => 'Type',
        'basis' => 'Base',
        'basis_percent' => 'Pourcentage du total de la facture',
        'basis_amount' => 'Montant fixe',
        'base_kind' => 'Base de calcul',
        'percent' => 'Pourcentage',
        'amount' => 'Montant fixe',
        'due_on' => 'Payable à partir du',
        'due_on_hint' => 'À partir de ce jour, la retenue est un poste ouvert normal et est de nouveau relancée.',
        'note' => 'Note',
        'heading' => 'Retenues de garantie',
        'action' => 'Enregistrer une retenue',
        'release' => 'Libérer',
        'column_kind' => 'Type',
        'column_amount' => 'Montant',
        'column_due' => 'Payable à partir du',
        'column_status' => 'Statut',
        'payable' => 'Montant à payer',
        'locked' => 'Les retenues de garantie ne peuvent être modifiées que sur un devis de facture — elles figurent sur le document et font partie de l’état figé après émission.',
        'needs_one_basis' => 'Veuillez indiquer soit un pourcentage, soit un montant fixe.',
        'no_total' => 'Le document n’a pas encore de total auquel rattacher une retenue.',
        'amount_positive' => 'La retenue doit être supérieure à zéro.',
        'exceeds_total' => 'Les retenues dépassent le total de la facture.',
        'not_open' => 'Cette retenue n’est plus ouverte.',
        'pdf_line' => 'moins :basis :kind selon § 17 VOB/B',
        'pdf_due' => 'payable à partir du :date',
        'pdf_payable' => 'Montant à payer',
        'dunning_note' => 'moins la retenue de garantie',
        'added' => 'Retenue enregistrée.',
        'released' => 'Retenue libérée.',
    ],

    // Leistungszeitraum je Position (Feature 152, Review 2026-09-11).
    // Freie Rechnungen aus Artikeln, Material und Fertigung (Feature 160, MVP-856–859).
    'free' => [
        'title' => [
            'create' => 'Créer une facture',
            'deliveries' => 'Reprendre des livraisons',
        ],
        'option' => [
            'manual' => 'Composer les lignes soi-même (articles, matériel, fabrication)',
            'no_variant' => '— sans variante —',
        ],
        'field' => [
            'variant' => 'Variante',
            'delivery' => 'Livraison',
            'order' => 'Ordre de fabrication',
            'delivered_on' => 'Livré le',
        ],
        'action' => [
            'attach_deliveries' => 'Reprendre une livraison',
            'open_order' => 'Ouvrir l\'ordre de fabrication',
        ],
        'hint' => [
            'manual' => 'Ni période ni temps : le brouillon démarre vide ; les lignes viennent des articles, du matériel, du texte libre ou des livraisons de fabrication.',
            'empty_draft' => 'Ajoutez des lignes ou reprenez une livraison. Un brouillon vide ne peut être ni émis ni envoyé.',
            'variant' => 'Facultatif ; la variante préremplit le prix et le numéro d\'article.',
            'no_stock_movement' => 'Les lignes d\'article et de texte libre ne mouvementent pas le stock ; une livraison est saisie via stock/livraison.',
            'price_required' => 'Saisir consciemment — 0,00 pour une ligne gratuite est admis.',
            'currency_mismatch' => 'Le prix de l\'article est dans une autre devise que le document ; saisissez le prix dans la devise du document.',
            'deliveries' => 'Livraisons effectuées et non facturées du client sur la période d\'en-tête :from – :to ; chaque livraison est reprise entièrement comme une ligne.',
            'deliveries_empty' => 'Les produits sans livraison se vendent comme ligne d\'article libre.',
            'deliveries_rules' => 'La quantité est liée à la source, le prix de vente vient de la livraison et reste modifiable dans le brouillon. Retirer la ligne ou abandonner le brouillon libère la livraison ; le stock reste inchangé.',
        ],
        'label' => [
            'from_order' => 'ordre de fabrication :number',
            'source_delivery' => 'Livraison du :date · :order · quantité livrée :quantity',
            'reserved' => 'réservée dans le brouillon',
        ],
        'empty' => [
            'deliveries' => 'Aucune livraison ouverte.',
        ],
        'flash' => [
            'draft_created' => 'Brouillon de facture créé — ajoutez maintenant des lignes.',
            'deliveries_attached' => ':count livraison(s) reprise(s).',
        ],
        'error' => [
            'empty' => 'Le brouillon n\'a aucune ligne et ne peut être ni émis ni envoyé.',
            'variant_mismatch' => 'La variante n\'appartient pas à l\'article choisi.',
            'draft_only' => 'Les livraisons ne se reprennent que dans un brouillon de facture normal.',
            'delivery_required' => 'Choisissez au moins une livraison.',
            'delivery_foreign' => 'La livraison n\'appartient pas à cette organisation.',
            'delivery_customer' => 'La livraison appartient à un autre client.',
            'delivery_external' => 'La livraison est facturée en externe.',
            'delivery_not_delivered' => 'La livraison n\'a pas encore eu lieu.',
            'delivery_invoiced' => 'La livraison est déjà facturée.',
            'delivery_reserved' => 'La livraison « :name » est déjà réservée dans le brouillon :number.',
            'delivery_currency' => 'La livraison est en :currency, le document en :invoice — pas de conversion automatique.',
            'delivery_project' => 'La livraison appartient à un autre projet.',
            'delivery_without_price' => 'La livraison « :name » n\'a pas de prix de vente — le renseigner sur l\'article/la variante ou saisir une ligne libre.',
        ],
    ],

    'item' => [
        'service_period' => 'Période de prestation',
        'service_from' => 'Période de prestation du',
        'service_to' => 'Période de prestation au',
    ],
    'service_rules' => [
        'title' => 'Règles de facturation',
        'hint' => 'Pour chaque type d’activité, vous pouvez définir l’article utilisé comme prestation lors du transfert et de l’export des factures — issu du fichier articles ou d’un logiciel comptable connecté. Sans type d’activité = règle par défaut pour toutes les saisies. Les sous-projets héritent des règles du projet parent mais peuvent les remplacer.',
    ],
];
