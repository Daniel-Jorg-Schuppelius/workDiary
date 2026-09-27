<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Schémas remarquables (défauts en série, lots)',
        'hint' => 'Groupes d’au moins :threshold réclamations dans la période. Une indication, pas une décision.',
        'none' => 'Aucun schéma remarquable dans la période.',
        'rule_label' => 'Règle',
        'group' => 'Groupe',
        'cases' => 'Dossiers',
        'count' => 'Nombre',
        'rule' => [
            'lot' => 'Lot',
            'article_defect' => 'Article × type de défaut',
            'article_cause' => 'Article × cause',
            'supplier_defect' => 'Fournisseur × type de défaut',
            'entry_type_cause' => 'Type de commande × cause',
        ],
        'notify_title' => 'Schéma de réclamations remarquable : :label',
        'notify_message' => ':count réclamations en :days jours (:rule).',
    ],
    // Retourenlabel einer RMA (MVP-917).
    'return_label' => [
        'title' => 'Étiquette de retour',
        'create' => 'Créer l\'étiquette de retour',
        'download' => 'Télécharger l\'étiquette',
        'created' => 'Étiquette de retour créée (envoi :tracking).',
        'no_address' => 'L\'étiquette de retour nécessite l\'adresse du client (rue, code postal, ville).',
    ],
    // Retourenanmeldung im Kundenportal (MVP-935).
    'portal_return' => [
        'capability' => 'Déclarer un retour',
        'nav' => 'Déclarer un retour',
        'title' => 'Déclarer un retour',
        'intro' => 'Choisissez la livraison ou l\'objet, décrivez le motif et joignez des photos si nécessaire. Vous recevrez un numéro de retour ; nous fournissons une étiquette de retour si besoin.',
        'empty' => 'Aucune livraison ni aucun objet pour votre compte.',
        'submit' => 'Déclarer le retour',
        'label' => 'Télécharger l\'étiquette de retour',
        'field' => [
            'delivery' => 'Livraison',
            'asset' => 'Objet',
            'serial_no' => 'Numéro de série',
            'quantity' => 'Quantité',
            'title' => 'Description courte',
            'description' => 'Motif du retour',
            'photos' => 'Photos ou justificatifs (5 au maximum)',
        ],
        'flash' => [
            'submitted' => 'Retour déclaré : réclamation :number, numéro de retour :rma.',
        ],
        'error' => [
            'subject' => 'Veuillez choisir une livraison ou un objet.',
            'serial' => 'Ce numéro de série n\'appartient pas à la livraison choisie.',
        ],
    ],
];
