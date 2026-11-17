<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Steuerfall der Übergabe (MVP-1712)
    'tax_case' => [
        'not_taxable_outside_third_country' => 'Pour les prestations non imposables en Allemagne, Lexoffice ne connaît que « prestations de services vers des pays tiers ». Si le lieu de prestation se trouve dans l’UE (par exemple un bien immobilier dans un autre pays de l’UE) ou dans plusieurs zones, il n’existe pas de type de taxe adapté — veuillez facturer dans workDiary ou saisir le document manuellement dans Lexoffice.',
        'contact_required' => 'Pour « :case », Lexoffice exige un contact créé dans Lexoffice ; le client n’y est pas encore lié. Veuillez d’abord transférer le contact.',
        'time_voucher' => 'Pour le cas fiscal « :case », l’interface des justificatifs de Lexoffice ne connaît aucune catégorie comptable (par exemple § 13b en Allemagne ou exonération selon le § 4 UStG). Veuillez transférer les temps vers Lexoffice via la remise de facturation, qui transmet le type de taxe adapté.',
        'time_voucher_mixed' => 'Les temps donnent plusieurs cas fiscaux (:case) ; un justificatif n’en porte qu’un. Veuillez facturer les temps séparément, par exemple via la remise de facturation par cas fiscal.',
        'time_voucher_currency' => 'Un justificatif Lexoffice ne contient que des montants en euros, mais le client est facturé en :currency. Veuillez facturer dans workDiary ou saisir le justificatif manuellement dans Lexoffice.',
    ],
    'error' => [
        'lexoffice_contact_missing' => 'Aucun contact Lexoffice pour le client — veuillez d\'abord synchroniser le contact.',
        'lexoffice_delivery_no_customer' => 'Une livraison sans client ne peut pas être transmise comme bon de livraison.',
        'lexoffice_delivery_not_linked' => 'Aucun bon de livraison Lexoffice n\'est lié à cette livraison.',
        'lexoffice_dunning_not_invoice' => 'Une relance ne peut être créée que pour une facture.',
        'lexoffice_not_configured' => 'Lexoffice n\'est pas configuré pour cette organisation (clé API manquante).',
        'lexoffice_outcome_unclear' => 'Issue du transfert Lexoffice incertaine (délai dépassé après l\'envoi) — ne pas relancer à l\'aveugle ; la prochaine exécution recherche le brouillon via le marqueur source.',
        'contact_billing_address' => 'Le contact « :customer » doit avoir exactement une adresse de facturation dans Lexoffice, mais en a :count. Veuillez corriger l’adresse là-bas et renouveler le transfert.',
        'lexoffice_oc_no_customer' => 'Un ordre de fabrication sans client ne peut pas être transmis comme confirmation de commande.',
        'lexoffice_oc_not_linked' => 'Aucune confirmation de commande Lexoffice n\'est liée à cet ordre de fabrication.',
        'lexoffice_quote_no_customer' => 'Un ordre de fabrication sans client ne peut pas être transmis comme devis.',
        'lexoffice_document_no_customer' => 'Un document sans client ne peut pas être transmis à Lexoffice.',
        'lexoffice_quote_not_linked' => 'Aucun devis Lexoffice n\'est lié à cet ordre de fabrication.',
    ],
    'lexoffice' => [
        'introduction' => 'Nous vous facturons nos livraisons et prestations comme suit.',
        'delivery_title' => 'Bon de livraison',
        'transfer_marker' => 'Référence de transfert :marker',
    ],
    'vouchers' => [
        'sync_now' => 'Synchroniser les pièces maintenant',
        'sync_confirm' => "Synchroniser maintenant les pièces avec Lexoffice ? L'exécution récupère en arrière-plan les pièces nouvelles et modifiées ; la synchronisation complète quotidienne n'est pas concernée.",
        'sync_hint' => "Récupère en arrière-plan les pièces Lexoffice nouvelles et modifiées — le pendant manuel de la synchronisation planifiée des pièces.",
    ],
];
