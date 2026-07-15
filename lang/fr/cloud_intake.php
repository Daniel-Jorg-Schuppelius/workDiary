<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Cloud-Dokumenteingang (Feature 080).
return [
    'validation' => [
        'pattern_empty' => 'Le motif de chemin ne doit pas être vide.',
        'pattern_triple_star' => 'Motif invalide : « *** » n\'est pas autorisé (seulement * et **).',
        'unknown_variable' => 'Variable de chemin inconnue :variable.',
        'duplicate_variable' => 'La variable de chemin :variable apparaît plusieurs fois.',
    ],
    'title' => [
        'index' => 'Entrée de documents cloud',
        'subtitle' => 'Lire les documents des dossiers cloud surveillés et les router vers les factures entrantes et la GED via des règles de dossiers.',
        'empty' => 'Aucune connexion cloud pour le moment.',
    ],
    'field' => [
        'provider' => 'Fournisseur',
        'name' => 'Nom',
        'account' => 'Compte',
        'root_folder' => 'Dossier racine',
        'routes' => 'Règles',
        'status' => 'Statut',
        'account_unconfirmed' => 'Compte pas encore confirmé',
        'container' => 'Conteneur/drive',
        'root_folder_id' => 'ID du dossier racine (optionnel)',
    ],
    'action' => [
        'connect_dropbox' => 'Connecter Dropbox',
        'connect_microsoft' => 'Connecter Microsoft 365',
        'connect_google' => 'Connecter Google Drive',
        'preview' => 'Aperçu',
        'save_folder' => 'Appliquer le dossier',
        'disconnect' => 'Déconnecter',
        'disconnect_confirm' => 'Vraiment déconnecter ? Les documents importés et les justificatifs restent ; seuls l\'accès et le point de reprise sont supprimés.',
    ],
    'flash' => [
        'not_configured' => 'Ce fournisseur n\'est pas configuré (clés d\'application manquantes).',
        'state_invalid' => 'Le processus de connexion a expiré ou est invalide — veuillez recommencer.',
        'oauth_denied' => 'L\'autorisation a été annulée.',
        'oauth_failed' => 'La connexion a échoué (:class).',
        'account_failed' => 'Le compte n\'a pas pu être confirmé (:class).',
        'connected' => 'Connexion établie — compte confirmé.',
        'folder_selected' => 'Dossier racine appliqué — la prochaine exécution repart d\'une synchronisation fraîche.',
        'overlapping_root' => 'Le dossier racine chevauche la connexion « :name » du même compte.',
        'preview_failed' => 'Aperçu échoué (:class).',
        'preview_result' => 'Aperçu (première page:more) : :files fichiers, :size — :matched avec règle, :unmatched sans affectation.',
        'disconnected' => 'Connexion supprimée — justificatifs et documents importés conservés.',
        'route_saved' => 'Règle de dossier enregistrée.',
        'route_deleted' => 'Règle de dossier supprimée.',
    ],
    'dropbox' => [
        'description' => 'Lit les documents des dossiers Dropbox surveillés (entrée de documents cloud) — avec règles de dossiers, justificatif de transfert et boîte de réception pour les cas ambigus.',
        'health' => [
            'not_configured' => 'Clés d\'application Dropbox non configurées.',
            'no_org_context' => 'Pas de contexte d\'organisation (exécution système).',
            'attention' => 'Au moins une connexion Dropbox demande attention (reconnexion/bloquée).',
            'ok' => 'Connexions Dropbox en bon état.',
            'error' => 'Vérification d\'état échouée (:class).',
        ],
    ],
    'google' => [
        'description' => 'Lit les documents des dossiers Google Drive surveillés (entrée de documents cloud) — Mon Drive et Drive partagés ; déploiement bloqué jusqu’à la vérification OAuth Google.',
        'health' => [
            'not_configured' => 'Clés client Google Drive non configurées.',
            'no_org_context' => 'Pas de contexte d’organisation (exécution système).',
            'attention' => 'Au moins une connexion Google Drive demande attention (reconnexion/bloquée).',
            'ok' => 'Connexions Google Drive en bon état.',
            'error' => 'Vérification d’état échouée (:class).',
        ],
    ],
    'route' => [
        'heading' => 'Règles de dossiers',
        'create' => 'Créer une règle',
        'edit' => 'Modifier la règle',
        'save' => 'Enregistrer',
        'delete' => 'Supprimer',
        'delete_confirm' => 'Vraiment supprimer cette règle ?',
        'basics' => 'Règle',
        'pattern' => 'Motif de chemin',
        'pattern_help' => '* = un segment, ** = toute profondeur ; variables : {customer_number}, {project_number}, {order_number}, {asset_number}, {contract_number}. Les cas ambigus vont dans la boîte d\'intégration.',
        'target' => 'Cible',
        'document_type' => 'Type de document',
        'priority' => 'Priorité',
        'extensions' => 'Extensions autorisées',
        'extensions_help' => 'Séparées par des virgules ; vide = toutes (sauf blocage global).',
        'max_size' => 'Taille max (octets)',
        'auto_version' => 'Adopter automatiquement les nouvelles révisions comme versions',
        'auto_version_help' => 'Sans validation, les nouvelles révisions deviennent des propositions de version dans la boîte.',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'empty' => 'Pas encore de règle — sans règle valide, la connexion n\'importe pas.',
    ],
    'log' => [
        'heading' => 'Journal d\'import',
        'empty' => 'Aucun transfert.',
        'path' => 'Chemin source',
        'revision' => 'Révision',
        'reason' => 'Motif',
        'when' => 'Date',
    ],
];
