<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Cibles de sauvegarde cloud',
    'description' => 'Copies hors site chiffrées de toute l\'installation (stratégie 3-2-1). Le texte en clair ne quitte jamais l\'installation — seules des parties chiffrées sont téléversées.',

    'master_key_missing' => 'BACKUP_MASTER_KEY n\'est pas défini — sans la clé de sauvegarde de l\'installation, aucune sauvegarde ne peut être créée ni restaurée.',
    'recovery_key_missing' => 'Aucune clé de récupération configurée : si BACKUP_MASTER_KEY est perdue, toutes les sauvegardes cloud sont irrémédiablement perdues. Définir BACKUP_RECOVERY_PUBLIC_KEY et conserver la clé privée hors ligne.',

    'connect' => 'Connecter',
    'reconnect' => 'Se reconnecter',
    'disconnect' => 'Déconnecter',
    'disconnect_confirm' => 'Vraiment déconnecter ? Les données distantes restent intactes ; les sauvegardes planifiées s\'arrêtent.',
    'cleanup' => 'Nettoyage',
    'no_connections' => 'Aucune cible de sauvegarde connectée pour le moment.',
    'account' => 'Compte',
    'quota' => 'Stockage',
    'quota_value' => ':used sur :total utilisés',
    'quota_unknown' => 'Utilisation du stockage inconnue',
    'pilot_note' => 'Pilote en attente : cet adaptateur n\'a pas encore été testé contre le vrai fournisseur.',

    'nextcloud' => [
        'connect_title' => 'Connecter Nextcloud',
        'connect_legend' => 'Identifiants',
        'connect_submit' => 'Connecter',
        'field' => [
            'name' => 'Nom',
            'server_url' => 'URL du serveur',
            'server_url_help' => 'HTTPS uniquement. Exemple : https://cloud.example.com',
            'username' => 'Nom d’utilisateur',
            'app_password' => 'Mot de passe d’application',
            'app_password_help' => 'Un mot de passe d’application révocable (Paramètres › Sécurité), jamais le mot de passe du compte.',
        ],
        'validation' => [
            'https_required' => 'L’URL du serveur doit commencer par https://.',
            'unsafe_url' => 'L’URL du serveur doit être accessible publiquement (pas de cible interne/privée).',
        ],
    ],
    'generations' => [
        'title' => 'Générations de sauvegarde',
        'empty' => 'Aucune génération de sauvegarde pour le moment.',
        'snapshot' => 'Instantané',
        'target' => 'Cible',
        'class' => 'Classe',
        'age' => 'Créée',
        'size' => 'Taille',
        'status' => 'Statut',
        'verified' => 'Vérifiée',
        'restore_tested' => 'Test de restauration',
        'restore_pending' => 'sauvegardée, restauration non confirmée',
        'hold' => 'Conservation légale',
        'actions' => 'Actions',
        'hold_set_action' => 'Activer la conservation',
        'hold_release_action' => 'Lever la conservation',
        'delete_action' => 'Supprimer',
        'delete_confirm' => 'Vraiment supprimer cette génération ? Les données distantes et l\'enregistrement seront retirés.',
    ],

    'cleanup_page' => [
        'title' => 'Nettoyage — inventaire distant',
        'description' => 'Aperçu des objets de la zone de sauvegarde de cette connexion. La suppression n\'a lieu qu\'après confirmation par génération.',
        'empty' => 'Aucun objet distant trouvé dans la zone de sauvegarde.',
        'known' => 'Génération connue',
        'orphan' => 'Orpheline (aucun enregistrement en base)',
        'error' => 'L\'inventaire distant n\'a pas pu être chargé : :message',
        'back' => 'Retour à la vue d\'ensemble',
    ],

    'flash' => [
        'not_configured' => 'Le fournisseur n\'est pas configuré (client ID/secret manquants).',
        'state_invalid' => 'Le processus de connexion a expiré ou est invalide — veuillez recommencer.',
        'oauth_denied' => 'L\'autorisation a été annulée ou refusée.',
        'oauth_failed' => 'Échange de jetons échoué (:class).',
        'account_failed' => 'Confirmation du compte échouée (:class).',
        'scope_missing' => 'Autorisation requise manquante (:scope) — la cible est bloquée.',
        'connected' => 'Cible de sauvegarde connectée et active.',
        'disconnected' => 'Connexion supprimée. Les données distantes restent intactes.',
        'hold_set' => 'Conservation légale activée — la génération est protégée contre la suppression.',
        'hold_released' => 'Conservation légale levée.',
        'hold_blocks_delete' => 'Cette génération porte une conservation légale et ne peut pas être supprimée.',
        'cleanup_failed' => 'Nettoyage distant échoué (:class).',
        'generation_deleted' => 'Génération retirée (distant et enregistrement).',
    ],
];
