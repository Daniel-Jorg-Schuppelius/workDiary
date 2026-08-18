<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gaeb.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Bordereaux des prix',
    'subtitle' => 'Importer des bordereaux GAEB et suivre les postes',
    'empty' => 'Aucun bordereau importé pour le moment.',
    'import_button' => 'Importer un fichier GAEB',

    'columns' => [
        'name' => 'Désignation',
        'project' => 'Projet',
        'phase' => 'Phase',
        'version' => 'Version GAEB',
        'items' => 'Postes',
        'reference_no' => 'Réf.',
        'short_text' => 'Texte court',
        'quantity' => 'Quantité',
        'unit' => 'Unité',
        'unit_price' => 'PU',
        'total_price' => 'Total',
        'type' => 'Type',
        'status' => 'Statut',
        'executed' => 'Métré',
        'remaining' => 'Reste',
    ],

    'import' => [
        'title' => 'Importer un fichier GAEB',
        'file' => 'Fichier GAEB DA XML',
        'file_hint' => 'GAEB DA XML 3.x (p. ex. .x81, .x83, .x86 ou .xml).',
        'project' => 'Projet (facultatif)',
        'project_none' => '— aucun projet —',
        'name' => 'Désignation (facultatif)',
        'name_hint' => 'Remplace le nom du projet issu du fichier.',
        'submit' => 'Importer',
        'status' => [
            'pending' => 'En vérification',
            'preflight_failed' => 'Échec du contrôle préalable',
            'imported' => 'Importé',
            'conflict' => 'Conflit',
        ],
    ],

    'show' => [
        'positions' => 'Postes',
        'history' => 'Historique des imports',
        'no_imports' => 'Aucun import enregistré.',
        'imported_at' => 'Importé le',
        'back' => 'Retour à la liste',
    ],

    'change_order' => [
        'phase' => [
            'CallChangOrder' => 'Demande d’avenant',
            'SupplBid' => 'Offre d’avenant',
            'SupplAgree' => 'Accord d’avenant',
        ],
        'initiator' => [
            'Owner' => 'Maître d’ouvrage',
            'Contractor' => 'Entrepreneur',
        ],
    ],
    'phase' => [
        '31' => 'Relevé des quantités',
        '50' => 'Catalogue des coûts de construction',
        '51' => 'Détermination des coûts',
        '52' => 'Données de calcul',
        '80' => 'Données universelles du devis',
        '81' => 'Devis descriptif',
        '82' => 'Estimation des coûts',
        '83' => 'Appel d\'offres',
        '84' => 'Remise de l\'offre',
        '85' => 'Offre variante',
        '86' => 'Attribution du marché',
        '87' => 'Confirmation de commande',
        '89' => 'Facture',
        '89B' => 'Pièce justificative de facture',
        '83Z' => 'Contrat-cadre : appel d\'offres',
        '84Z' => 'Contrat-cadre : remise de l\'offre',
        '86ZE' => 'Contrat-cadre : commande individuelle',
        '86ZR' => 'Contrat-cadre : commande cadre',
        '93' => 'Demande de prix',
        '94' => 'Offre de prix',
        '96' => 'Commande',
        '97' => 'Confirmation de commande (négoce)',
    ],

    'item' => [
        'type' => [
            'standard' => 'Poste normal',
            'base' => 'Poste de base',
            'alternative' => 'Poste alternatif',
            'optional' => 'Poste optionnel',
            'lump_sum' => 'Poste forfaitaire',
            'markup' => 'Poste de majoration',
            'note' => 'Remarque',
        ],
        'status' => [
            'draft' => 'Brouillon',
            'imported' => 'Importé',
            'quoted' => 'Offert',
            'ordered' => 'Commandé',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'replaced' => 'Remplacé',
            'cancelled' => 'Annulé',
        ],
        'change_order_status' => [
            'Recog' => 'Reconnu',
            'Filed' => 'Déclaré',
            'Offered' => 'Proposé',
            'Withdrawn' => 'Retiré',
            'Rejected' => 'Rejeté',
            'ObjToRecj' => 'Opposition au rejet',
            'FormAckn' => 'Reconnu sur le fond',
            'Approved' => 'Approuvé',
        ],
    ],

    'preflight' => [
        'version_unknown' => 'La version GAEB n’a pas pu être détectée.',
        'version_unsupported' => 'La version GAEB :version n’est pas prise en charge (cible 3.3).',
        'phase_unknown' => 'La phase d’échange « :code » est inconnue.',
        'no_items' => 'Le fichier ne contient aucun poste.',
        'vendor_record_type' => 'Le fichier contient :count enregistrements du type propriétaire :type — leur contenu n’est pas exploité (certains systèmes y placent les groupes de coûts).',
        'item_missing_ref' => 'Poste sans numéro d’ordre : :text',
        'duplicate_ref' => 'Le numéro d’ordre :ref apparaît plusieurs fois.',
        'missing_quantity' => 'Le poste :ref n’a pas de quantité.',
        'non_positive_quantity' => 'Le poste :ref a une quantité ≤ 0.',
        'missing_unit' => 'Le poste :ref n’a pas d’unité.',
        'missing_price' => 'Le poste :ref n’a pas de prix unitaire dans une phase avec prix.',
        'unpriced_item' => 'Le poste :ref n’est ni chiffré ni marqué « non proposé » dans l’offre.',
        'priced_but_not_offered' => 'Le poste :ref est marqué « non proposé » mais porte un prix unitaire.',
        'up_components_mismatch' => 'Poste :ref : la somme des composantes du prix unitaire (:sum) diffère du prix unitaire (:price).',
        'missing_text' => 'Le poste :ref n’a pas de texte court/long.',
        'total_mismatch' => 'Le total indiqué (:stated) diffère du total recalculé (:computed).',
        'complement_empty' => 'Poste :ref : le complément de texte du soumissionnaire :mark n\'est pas rempli.',
        'contractor_missing' => 'Cette phase exige l\'adresse du soumissionnaire (nom, rue, code postal et ville dans les données de facturation électronique).',
    ],

    'flash' => [
        'imported' => 'Bordereau importé avec :items postes.',
        'preflight_failed' => 'Import interrompu : :count erreurs de contrôle préalable. Aucun poste n’a été écrit.',
        'conflict' => 'Réimport interrompu : des postes en cours d’exécution (:refs) seraient écrasés.',
    ],

    'progress' => [
        'from_takeoff' => 'Quantité recalculée à partir de :lines lignes de métré du X31.',
        'takeoff_skipped' => ':count lignes avec une formule non prise en charge ont été ignorées.',
        'title' => 'Métré / avancement',
        'record' => 'Saisir un métré',
        'quantity' => 'Quantité',
        'note' => 'Note',
        'source' => [
            'manual' => 'Manuel',
            'measurement' => 'Métré',
            'protocol' => 'Procès-verbal',
            'material' => 'Consommation de matériel',
        ],
        'flash' => [
            'recorded' => 'Métré enregistré.',
        ],
    ],

    'mapping' => [
        'title' => 'Liaison',
        'add' => 'Lier',
        'target_type' => 'Type de cible',
        'article' => 'Article',
        'material' => 'Matériel',
        'factor' => 'Facteur',
        'flash' => [
            'linked' => 'Poste lié.',
        ],
    ],

    'workflow' => [
        'status' => 'Définir le statut',
        'add_addendum' => 'Ajouter un avenant',
        'remaining_title' => 'Travaux restants',
        'no_remaining' => 'Aucun travail restant ouvert.',
        'flash' => [
            'item_updated' => 'Statut du poste modifié.',
            'bill_updated' => 'Statut du bordereau modifié.',
            'addendum_added' => 'Avenant ajouté.',
        ],
    ],

    'costing' => [
        'title' => 'Suivi des coûts',
        'planned' => 'Prévu',
        'executed' => 'Réel (métré)',
        'remaining' => 'Reste',
        'progress' => 'Avancement',
    ],

    'export' => [
        'button' => 'Exporter GAEB',
        'title' => 'Export GAEB',
        'phase' => 'Phase',
    ],
    'trade' => [
        'missing_own_address' => 'Votre propre adresse manque dans les données de facturation électronique ; sans elle, le fichier n’identifie pas l’acheteur.',
        'missing_delivery_date' => 'Sans date de livraison, la commande reste indéterminée ; la date de commande a été utilisée.',
        'missing_supplier_sku' => ':count ligne(s) sans référence article du fournisseur — son système ne trouve la marchandise que par elle.',
        'missing_supplier_tax_no' => 'Le fournisseur n’a ni numéro fiscal ni numéro de TVA.',
    ],
    'invoice' => [
        'share_net' => 'Montant net',
        'share_discount' => 'Remise',
        'share_vat' => 'TVA :rate %',
        'missing_tax_number' => 'Le numéro fiscal et le numéro de TVA manquent dans les données de facturation électronique — la loi fiscale en exige un.',
        'missing_recipient' => 'L’adresse du destinataire de la facture est incomplète.',
        'missing_service_period' => 'Aucune période de prestation déductible ; la date de facture a été utilisée.',
    ],
    'comparison' => [
        'title' => 'Comparatif des prix',
        'spread' => 'Écart',
        'rank' => 'Rang :rank',
        'gap' => ':percent % moins cher que l’offre suivante',
        'unusually_low_hint' => 'Une offre anormalement basse appelle une clarification, pas une exclusion (§ 16d VOB/A, § 60 VgV).',
        'incomplete_hint' => 'Tous les soumissionnaires n’ont pas chiffré chaque poste — les prix manquants sont des lacunes, pas des zéros.',
        'empty_title' => 'Aucune offre',
        'empty_hint' => 'Importez des offres (X84) pour cet appel afin de les comparer.',
        'button' => 'Comparatif des prix',
    ],
];
