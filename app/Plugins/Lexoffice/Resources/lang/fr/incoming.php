<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rechnungseingang → Lexware Office (Feature 163, MVP-1111).

return [
    'label' => 'Lexware Office',
    'amount_differs' => 'Lexware contient déjà le justificatif :number avec un autre montant. Veuillez le vérifier là-bas, puis relancer le transfert.',
    'remark' => 'Issu des factures reçues de WorkDiary (:sender).',
    'no_party' => 'Le document n’est encore attribué à aucune partie.',
    'contact_missing' => 'Le contact :party n’a pu être ni trouvé ni créé dans Lexware.',
    'contact_role' => 'Lexware refuse le contact :party en raison de son rôle. Veuillez ajouter le rôle fournisseur ou client dans Lexware, puis relancer le transfert.',
    'header_only' => [
        'no_category' => 'Transféré sans montants : aucune catégorie comptable n’est définie pour la partie ni dans les paramètres.',
        'currency' => 'Transféré sans montants : Lexware n’accepte que des justificatifs en euros.',
        'rates' => 'Transféré sans montants : les taux de TVA ne correspondent pas à Lexware ou les totaux sont incomplets.',
    ],
    'settings' => [
        'transfer' => 'Transférer les factures reçues vers Lexware Office',
        'transfer_help' => 'Les documents attribués arrivent dans Lexware « à vérifier ». Si un justificatif portant le même numéro y existe déjà, il est seulement lié.',
        'incoming_category' => 'Catégorie par défaut des documents entrants',
        'outgoing_category' => 'Catégorie par défaut des documents sortants',
        'category_help' => 'Sans catégorie, les justificatifs arrivent dans Lexware sans montants. Une catégorie sur le fournisseur ou le client est prioritaire. La liste provient de la synchronisation des catégories.',
        'no_category' => '— aucune —',
    ],
    'category' => [
        'title' => 'Catégorie comptable dans Lexware',
        'help' => 'Les documents des factures reçues arrivent dans Lexware avec cette catégorie au lieu de la catégorie par défaut des paramètres.',
        'save' => 'Enregistrer la catégorie',
        'saved' => 'Catégorie comptable enregistrée.',
        'cleared' => 'Catégorie comptable supprimée ; la valeur par défaut des paramètres s’applique.',
    ],
];
