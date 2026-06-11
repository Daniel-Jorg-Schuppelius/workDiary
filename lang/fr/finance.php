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
        'total' => 'Total',
    ],

    'lexoffice' => [
        'introduction' => 'Remise depuis WorkDiary — :channel, période :from – :to.',
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
        'sources_missing' => 'Les sources de ce justificatif de transfert ne sont plus toutes disponibles.',
    ],
];
