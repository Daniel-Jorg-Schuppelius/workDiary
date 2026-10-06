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
    'error' => [
        'lexoffice_contact_missing' => 'Aucun contact Lexoffice pour le client — veuillez d\'abord synchroniser le contact.',
        'lexoffice_delivery_no_customer' => 'Une livraison sans client ne peut pas être transmise comme bon de livraison.',
        'lexoffice_delivery_not_linked' => 'Aucun bon de livraison Lexoffice n\'est lié à cette livraison.',
        'lexoffice_dunning_not_invoice' => 'Une relance ne peut être créée que pour une facture.',
        'lexoffice_not_configured' => 'Lexoffice n\'est pas configuré pour cette organisation (clé API manquante).',
        'lexoffice_outcome_unclear' => 'Issue du transfert Lexoffice incertaine (délai dépassé après l\'envoi) — ne pas relancer à l\'aveugle ; la prochaine exécution recherche le brouillon via le marqueur source.',
        'lexoffice_oc_no_customer' => 'Un ordre de fabrication sans client ne peut pas être transmis comme confirmation de commande.',
        'lexoffice_oc_not_linked' => 'Aucune confirmation de commande Lexoffice n\'est liée à cet ordre de fabrication.',
        'lexoffice_quote_no_customer' => 'Un ordre de fabrication sans client ne peut pas être transmis comme devis.',
        'lexoffice_quote_not_linked' => 'Aucun devis Lexoffice n\'est lié à cet ordre de fabrication.',
    ],
    'lexoffice' => [
        'introduction' => 'Nous vous facturons nos livraisons et prestations comme suit.',
        'delivery_title' => 'Bon de livraison',
        'transfer_marker' => 'Référence de transfert :marker',
    ],
];
