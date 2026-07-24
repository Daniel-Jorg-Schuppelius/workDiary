<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'module' => 'Interface financière',
        'transfers' => 'Justificatifs de transfert',
        'transfer' => 'Justificatif de transfert',
        'menu' => 'Remise de facturation',
        'positions' => 'Positions générées',
        'sources' => 'Sources individuelles (instantané)',
        'events' => 'Journal des événements',
    ],

    'subtitle' => [
        'transfers' => 'Remettre les temps et matériaux facturables au système de facturation principal, par canaux séparés.',
    ],

    'field' => [
        'billing_mode' => 'Canal de facturation',
        'billing_mode_inherit' => '— Hériter du standard de l\'organisation —',
        'billing_mode_default' => '— WorkDiary (par défaut) —',
        'billing_mode_hint' => 'Remplace le standard de l\'organisation pour ce client. Avec Lexoffice/DATEV, la facturation locale est verrouillée.',
        'billing_mode_org_hint' => 'Canal de facturation par défaut de l\'organisation. Les clients peuvent le remplacer individuellement.',
        'channel' => 'Canal de transfert',
        'target' => 'Cible de transfert',
        'status' => 'Statut',
        'period' => 'Période de prestation',
        'position_count' => 'Positions',
        'total_amount' => 'Montant total (net)',
        'total_quantity' => 'Quantité totale',
        'payload_hash' => 'Hash de la charge utile',
        'transferred_at' => 'Transféré le',
        'failure_reason' => 'Motif de l\'échec',
        'customer' => 'Client',
        'source' => 'Source',
        'source_deleted' => 'Source plus disponible',
    ],

    'action' => [
        'create_draft' => 'Préparer le transfert',
        'confirm' => 'Confirmer le transfert',
        'mark_transferred' => 'Marquer comme transféré',
        'mark_failed' => 'Marquer comme échoué',
        'void' => 'Annuler le transfert',
        'show' => 'Afficher',
        'execute' => 'Transférer maintenant',
        'retry' => 'Réessayer',
        'download' => 'Télécharger le paquet de remise',
        'open_external' => 'Ouvrir en externe',
    ],

    'filter' => [
        'all' => 'Tous',
    ],

    'hint' => [
        'channels_separate' => 'Le temps et le matériel sont confirmés comme paquets de remise séparés.',
        'datev_desktop_api' => 'DATEV pilote : remise sous forme de paquet fichier (CSV) — l\'API DATEV Desktop suivra comme adaptateur séparé.',
        'target_by_mode' => 'La cible est présélectionnée à partir du canal de facturation du client.',
        'period_sources' => 'Seules les sources facturables, pas encore facturées/remises dans la période, sont collectées.',
        'lexoffice_draft_created' => 'Brouillon de facture créé dans Lexoffice :',
        'sevdesk_draft_created' => 'Brouillon de facture créé dans sevDesk :',
        'easybill_draft_created' => 'Brouillon de facture créé dans easybill :',
    ],

    'confirm_execute' => 'Transférer maintenant vers la cible ? En cas de succès, les sources seront marquées comme remises.',
    'confirm_void' => 'Annuler ce transfert ? Les sources seront de nouveau libérées.',

    'empty_title' => 'Aucun justificatif de transfert',
    'empty_message' => 'Aucun transfert n\'a encore été préparé.',
    'empty_filtered' => 'Aucun transfert pour les filtres sélectionnés.',
    'empty_positions_title' => 'Aucune position',
    'empty_positions' => 'Les sources ne génèrent aucune position (p. ex. sources supprimées).',

    'csv' => [
        'package_title' => 'Paquet de remise WorkDiary (CSV) — pas un format DATEV',
        'position' => 'Position',
        'date' => 'Date',
        'employee' => 'Collaborateur',
        'project' => 'Projet/Commande',
        'activity' => 'Activité',
        'hours' => 'Heures',
        'rate' => 'Taux',
        'amount' => 'Montant',
        'comment' => 'Commentaire',
        'product' => 'Produit',
        'quantity' => 'Quantité',
        'unit' => 'Unité',
        'unit_price_net' => 'Prix unitaire net',
        'tax_rate' => 'Taux de taxe',
        'cost_position' => 'Poste de coût',
        'total' => 'Total',
    ],

    'easybill' => [
        'introduction' => 'Transfert depuis WorkDiary — :channel, période :from – :to.',
        'unit_hour' => 'h',
        'unit_piece' => 'pcs',
    ],

    'sevdesk' => [
        'introduction' => 'Remise depuis WorkDiary — :channel, période :from – :to.',
        'tax_text' => 'TVA :rate %',
    ],

    'lexoffice' => [
        'introduction' => 'Remise depuis WorkDiary — :channel, période :from – :to.',
        'delivery_title' => 'Bon de livraison',
    ],

    'flash' => [
        'created' => 'Brouillon du justificatif de transfert créé.',
        'confirmed' => 'Transfert confirmé.',
        'transferred' => 'Transfert terminé — les sources ont été marquées comme transférées.',
        'failed' => 'Transfert marqué comme échoué.',
        'voided' => 'Transfert annulé — les sources ont été libérées.',
    ],

    'error' => [
        'local_invoicing_locked' => 'La facturation est pilotée par :program ; la création locale de factures est verrouillée.',
        'no_sources' => 'Aucune source transférable trouvée dans la période sélectionnée.',
        'illegal_transition' => 'Le changement de statut de « :from » vers « :to » n\'est pas autorisé.',
        'void_after_transfer' => 'Un transfert déjà livré ne peut pas être annulé — veuillez utiliser un transfert d\'annulation/de différence.',
        'entry_already_transferred' => 'L\'entrée de temps a déjà été remise à la facturation et ne peut plus être corrigée.',
        'target_not_allowed' => 'Cette cible n\'est pas autorisée pour le canal de facturation « :mode ».',
        'lexoffice_not_configured' => 'Lexoffice n\'est pas configuré pour cette organisation (clé API manquante).',
        'sevdesk_not_configured' => 'sevDesk n\'est pas configuré pour cette organisation (jeton API manquant).',
        'sevdesk_outcome_unclear' => 'Résultat de la remise sevDesk incertain (délai dépassé après l\'envoi) — ne pas réessayer aveuglément ; le prochain passage rapproche via le marqueur source.',
        'easybill_not_configured' => 'easybill n\'est pas configuré pour cette organisation (clé API manquante).',
        'easybill_outcome_unclear' => 'Résultat du transfert easybill incertain (délai dépassé après l\'envoi) — ne pas réessayer à l\'aveugle ; la prochaine exécution réconcilie via le marqueur source.',
        'lexoffice_contact_missing' => 'Aucun contact Lexoffice pour le client — veuillez d\'abord synchroniser le contact.',
        'lexoffice_delivery_no_customer' => 'Une livraison sans client ne peut pas être transmise comme bon de livraison.',
        'lexoffice_delivery_not_linked' => 'Aucun bon de livraison Lexoffice n\'est lié à cette livraison.',
        'lexoffice_oc_no_customer' => 'Un ordre de fabrication sans client ne peut pas être transmis comme confirmation de commande.',
        'lexoffice_oc_not_linked' => 'Aucune confirmation de commande Lexoffice n\'est liée à cet ordre de fabrication.',
        'lexoffice_quote_no_customer' => 'Un ordre de fabrication sans client ne peut pas être transmis comme devis.',
        'lexoffice_quote_not_linked' => 'Aucun devis Lexoffice n\'est lié à cet ordre de fabrication.',
        'lexoffice_dunning_not_invoice' => 'Une relance ne peut être créée que pour une facture.',
        'sources_missing' => 'Les sources de ce justificatif de transfert ne sont plus toutes disponibles.',
    ],
    'datev' => [
        'title' => 'Lot d\'écritures DATEV',
        'menu' => 'Lot d\'écritures DATEV',
        'subtitle' => 'Transmettez les factures émises, les avoirs et les frais validés d\'une période clôturée sous forme de lot d\'écritures DATEV (V700) vérifiable.',
        'empty' => 'Aucun lot d\'écritures créé pour le moment.',
        'empty_sources' => 'Aucune écriture dans ce lot.',
        'field' => [
            'batch_no' => 'N° de lot', 'period' => 'Période', 'status' => 'Statut',
            'booking_count' => 'Écritures', 'total' => 'Total', 'hash' => 'Empreinte du fichier (SHA-256)',
            'open_ready' => 'Justificatifs ouverts prêts à comptabiliser', 'document_ref' => 'Champ justificatif 1',
            'soll_haben' => 'D/C', 'account' => 'Compte', 'contra_account' => 'Contrepartie',
            'tax_key' => 'Clé BU', 'amount' => 'Montant (brut)', 'lock_flag' => 'Verrouillage',
            'include_expenses' => 'Inclure les frais validés', 'debtor_no' => 'Numéro de débiteur (DATEV)',
            'debtor_no_hint' => 'Laissez vide pour déduire automatiquement le numéro à partir de la plage de numérotation configurée.',
            'include_reversals' => 'Transmettre les justificatifs annulés (extourne générale)',
            'reversal' => 'GU',
            'reversal_badge' => 'Extourne générale',
        ],
        'metric' => [
            'draft' => 'Lots en brouillon',
            'exported' => 'Lots exportés',
            'exported_total_year' => 'Total exporté (année en cours)',
        ],
        'lock' => ['on' => 'verrouillé', 'off' => 'non verrouillé'],
        'selection' => ['manual' => 'Sélection partielle'],
        'action' => ['create' => 'Créer un lot', 'finalize' => 'Finaliser', 'download' => 'Télécharger le CSV', 'configure' => 'Configuration', 'save_config' => 'Enregistrer la configuration', 'discard' => 'Abandonner le brouillon', 'remove_selected' => 'Retirer la sélection', 'select_source' => 'Sélectionner l\'écriture'],
        'dialog' => ['create_title' => 'Créer un lot d\'écritures DATEV', 'create_hint' => 'Les justificatifs de la période prêts à comptabiliser sont rassemblés. Les factures gérées en externe sont exclues.'],
        'hint' => ['period_sources' => 'Sont prises en compte les factures émises/payées dont la date du justificatif tombe dans la période et qui ne figurent encore dans aucun lot finalisé.', 'include_expenses' => 'Facultatif : reprendre en plus les frais validés en tant qu\'écriture de charge (MVP — comptes simplifiés).', 'include_reversals' => 'Facultatif : retransmettre les justificatifs annulés déjà transférés en tant qu\'écritures d\'extourne générale.'],
        'flash' => ['created' => 'Lot d\'écritures créé en tant que brouillon.', 'finalized' => 'Lot d\'écritures finalisé — CSV généré et sources marquées comme transmises.', 'config_saved' => 'Configuration comptable enregistrée.', 'sources_removed' => 'Écritures sélectionnées retirées — elles sont de nouveau disponibles pour le prochain lot.', 'discarded' => 'Brouillon du lot abandonné — ses sources sont de nouveau disponibles.'],
        'error' => ['no_sources' => 'Aucun justificatif prêt à comptabiliser trouvé dans la période sélectionnée.', 'already_finalized' => 'Le lot d\'écritures est déjà finalisé et immuable.', 'storage_failed' => 'Le fichier DATEV n\'a pas pu être enregistré.', 'unavailable' => 'L\'export DATEV est un module complémentaire optionnel et payant, non activé dans cette installation. Son activation est possible sur demande à :contact.',
            'unavailable_badge' => 'Module complémentaire non activé', 'preflight_failed' => 'Le lot ne peut pas être finalisé en raison d\'erreurs de contrôle préalable.', 'no_organization' => 'Aucune organisation n\'a pu être résolue.', 'roundtrip_failed' => 'Le fichier DATEV généré n\'a pas réussi le contrôle de relecture : :errors', 'no_selection' => 'Aucune écriture sélectionnée.', 'selection_empty_batch' => 'Au moins une écriture doit rester dans le lot.', 'source_already_exported' => 'Le justificatif :ref fait déjà partie d\'un autre lot exporté.'],
        'preflight' => ['no_sources' => 'Le lot ne contient aucune écriture.', 'missing_client_numbers' => 'Le numéro de conseiller et le numéro de client doivent être renseignés dans la configuration.', 'missing_debtor' => 'Le justificatif :ref n\'a pas de compte de débiteur valide.', 'missing_revenue' => 'Le justificatif :ref n\'a pas de compte de produits.', 'unknown_tax_key' => 'Justificatif :ref : aucune clé BU renseignée pour le taux de taxe de :rate %.', 'external_excluded' => ':count facture(s) gérée(s) en externe ont été exclues du lot d\'écritures local.'],
        'roundtrip' => ['unsupported' => 'Le fichier n\'a pas été reconnu comme un format DATEV pris en charge.', 'version_mismatch' => 'Version de format DATEV inattendue (:version au lieu de 700).', 'parse_failed' => 'Le fichier généré n\'a pas pu être relu : :message', 'row_count_mismatch' => 'Les lignes d\'écriture relues (:actual) diffèrent du nombre attendu (:expected).'],
        'format' => ['label' => 'Format', 'value' => 'Lot d\'écritures DATEV (EXTF V700)', 'encoding' => 'Jeu de caractères', 'verified' => 'Contrôle de relecture réussi'],
        'loss' => ['title' => 'Champs dérivés et simplifiés', 'hint' => 'Ces champs sont dérivés ou représentés de manière simplifiée pour l\'export DATEV et doivent être vérifiés avant le transfert.', 'booking_date' => 'Date du justificatif = début de période (dérivée de la période du lot, pas par justificatif).', 'expense_account' => 'Les frais sont comptabilisés de manière simplifiée sur le compte de produits/charges (sans comptes de charges/TVA déductible différenciés par catégorie).', 'missing_tax_key' => 'Les justificatifs sans clé BU sont transférés sans ventilation de la taxe.'],
        'config' => ['title' => 'Configuration comptable DATEV', 'subtitle' => 'Numéro de conseiller/client, plan comptable, comptes généraux, plage de numérotation des débiteurs et clés de taxe par organisation.', 'client_group' => 'Conseiller & client', 'advisor_number' => 'Numéro de conseiller', 'client_number' => 'Numéro de client', 'accounts_group' => 'Comptes', 'skr' => 'Plan comptable', 'account_length' => 'Longueur des comptes généraux', 'revenue_account' => 'Compte de produits (par défaut)', 'revenue_account_tax_free' => 'Compte de produits (exonéré / 0 %)', 'debtor_base' => 'Base de la plage de numérotation des débiteurs', 'debtor_base_hint' => 'En l\'absence d\'un numéro de débiteur explicite chez le client, il est formé à partir de la base + l\'ID du client.', 'tax_group' => 'Clés de taxe (BU DATEV)', 'tax_key_19' => 'Clé BU 19 %', 'tax_key_7' => 'Clé BU 7 %', 'tax_key_0' => 'Clé BU 0 % / exonéré', 'export_group' => 'Export', 'finalize' => 'Définir l\'indicateur de verrouillage (GoBD)', 'finalize_hint' => 'Marque les écritures comme verrouillées lors de l\'export.', 'encoding' => 'Jeu de caractères', 'encoding_hint' => 'Le standard DATEV est ISO-8859-1 ; UTF-8 uniquement si explicitement souhaité.', 'expense_group' => 'Comptes de charges/TVA déductible par catégorie de frais', 'expense_group_hint' => 'Laisser vide pour utiliser la représentation simplifiée (slot du compte de produits + clé BU par taux). BU de TVA déductible p. ex. 9 = 19 %, 8 = 7 %.', 'expense_category' => 'Catégorie de frais', 'expense_account' => 'Compte de charges', 'expense_tax_key' => 'Clé BU de TVA déductible'],
    ],
];
