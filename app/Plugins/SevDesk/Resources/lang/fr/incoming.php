<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rechnungseingang → sevDesk (Feature 163, MVP-1507).

return [
    'label' => 'sevDesk',
    'amount_differs' => 'sevDesk contient déjà la pièce :number avec un autre montant. Veuillez la vérifier là-bas, puis relancer le transfert.',
    'candidate' => 'sevDesk contient probablement déjà la même pièce (:voucher), mais sans numéro de facture ce n’est pas certain. Veuillez la lier ou faire créer une nouvelle pièce.',
    'no_party' => 'La réception n’est encore attribuée à aucune partie.',
    'contact_missing' => 'Le contact :party n’a pu être ni trouvé de façon univoque ni créé dans sevDesk. Veuillez lier le contact dans sevDesk, puis relancer le transfert.',
    'no_account' => 'Aucun compte comptable n’est défini pour ce sens dans les paramètres sevDesk.',
    'currency' => 'sevDesk ne reçoit de la réception des factures que des pièces en euros. Veuillez saisir la pièce dans sevDesk ; le prochain transfert la liera via le numéro de facture.',
    'rates' => 'Les taux de TVA ne conviennent pas à sevDesk (0, 7 ou 19 %) ou les totaux sont incomplets. Veuillez saisir la pièce dans sevDesk ; le prochain transfert la liera via le numéro de facture.',
    'tax_case' => 'L’autoliquidation, les cas intracommunautaires et les exportations sont comptabilisés par la comptabilité dans sevDesk. Veuillez y saisir la pièce ; le prochain transfert la liera via le numéro de facture.',
    'xml_requires_update' => 'sevDesk n’accepte les XRechnung qu’à partir de la version comptable 2.0, et aucun PDF n’est joint à la réception.',
    'settings' => [
        'transfer' => 'Transférer la réception des factures à sevDesk',
        'transfer_help' => 'Les réceptions attribuées partent vers sevDesk comme brouillon avec l’original. Si une pièce portant le même numéro y existe déjà, elle est seulement liée.',
        'incoming_account' => 'Compte comptable des pièces entrantes (ID)',
        'outgoing_account' => 'Compte comptable des pièces sortantes (ID)',
        'account_help' => 'ID du compte dans le plan comptable sevDesk : AccountDatev en version comptable 2.0, AccountingType en 1.0. Sans compte, le transfert attend.',
    ],
];
