<?php

return [
    'created' => 'Créé',
    'updated' => 'Mis à jour',
    'deleted' => 'Supprimé',
    'archived' => 'Archivé',
    'restored' => 'Restauré',
    'auth' => [
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'failed' => 'Échec de connexion',
        'password_reset' => 'Réinitialisation du mot de passe',
    ],
    'onboarding' => [
        'completed' => 'Onboarding terminé',
        'stepCompleted' => 'Étape d\'onboarding terminée',
        'stepSkipped' => 'Étape d\'onboarding ignorée',
        'widgetDismissed' => 'Widget d\'onboarding masqué',
    ],
    'backup' => [
        'completed' => 'Sauvegarde terminée',
    ],
    'import' => [
        'confirmed' => 'Import confirmé',
        'started' => 'Import démarré',
        'finished' => 'Import terminé',
        'partial' => 'Import partiellement terminé',
        'preflightFailed' => 'Contrôle préalable de l\'import échoué',
    ],
    'diagnostics' => [
        'viewed' => 'Diagnostics consultés',
        'testTriggered' => 'Test de diagnostic déclenché',
    ],
    'role' => [
        'created' => 'Rôle créé',
        'updated' => 'Rôle mis à jour',
        'deleted' => 'Rôle supprimé',
    ],
    'user_group' => [
        'member_added' => 'Groupe d\'utilisateurs : membre ajouté',
        'member_removed' => 'Groupe d\'utilisateurs : membre retiré',
    ],
    // Attribution de rôles/permissions (Bauturbo A17, MVP-335)
    'user' => [
        'role' => [
            'assigned' => 'Rôle attribué',
            'revoked' => 'Rôle retiré',
        ],
        'permission' => [
            'granted' => 'Permission accordée',
            'revoked' => 'Permission retirée',
        ],
    ],
    'support' => [
        'test' => 'Test de support',
        'reportGenerated' => 'Rapport de support généré',
        'reportDownloaded' => 'Rapport de support téléchargé',
    ],
    'report' => [
        'exported' => 'Rapport exporté',
    ],
    'limit' => [
        'exceeded' => 'Limite dépassée',
    ],
    'license' => [
        'installed' => 'Licence installée',
    ],
    'asset' => [
        'created' => 'Ressource créée',
    ],
    'protocol' => [
        'signatureRequested' => 'Signature demandée',
        'signatureLinkOpened' => 'Lien de signature ouvert',
    ],
    'session' => [
        'revoked' => 'Session révoquée',
    ],
    'token' => [
        'revoked' => 'Jeton révoqué',
    ],
    // ArbZG-Compliance-Verstöße (Feature 006, Welle D)
    'compliance' => [
        'finding' => [
            'detected' => 'Infraction détectée',
            'acknowledged' => 'Infraction acquittée',
            'accepted' => 'Infraction acceptée',
            'resolved' => 'Infraction résolue',
            'reopened' => 'Infraction réapparue',
        ],
    ],
    'privacy' => [
        'overviewExported' => 'Aperçu de la protection des données exporté',
        'report' => [
            'exported' => 'Rapport de protection des données exporté',
        ],
    ],
    'integration' => [
        'changed' => 'Intégration activée/désactivée',
    ],
    'tenant' => [
        'export' => [
            'requested' => 'Export du locataire demandé',
        ],
    ],
    'branch_profile' => [
        'installed' => 'Profil de filiale installé',
    ],
    'demo' => [
        'reset' => 'Locataire de démonstration réinitialisé',
        'seeded' => 'Données de démonstration générées',
    ],
    'dayClose' => [
        'opened' => 'Clôture journalière ouverte',
        'entrySaved' => 'Clôture journalière enregistrée',
        'closed' => 'Jour clôturé',
        'correctionRequested' => 'Correction du jour demandée',
        'correctionApproved' => 'Correction du jour approuvée',
        'correctionRejected' => 'Correction du jour refusée',
        'reopened' => 'Jour rouvert',
    ],
    // Saisies de temps (MVP-508)
    'timeEntry' => [
        'reassigned' => 'Saisie de temps réattribuée à un autre utilisateur',
    ],
    // Accès au portail client (MVP-510)
    'portal' => [
        'query' => [
            'withdrawn' => 'Question du portail retirée',
        ],
        'visibility' => [
            'updated' => 'Visibilité du portail modifiée',
        ],
        'access' => [
            'invited' => 'Accès au portail invité',
            'invite_resent' => 'Invitation au portail renvoyée',
            'invite_accepted' => 'Invitation au portail acceptée',
            'deactivated' => 'Accès au portail désactivé',
            'reactivated' => 'Accès au portail réactivé',
        ],
    ],
];
