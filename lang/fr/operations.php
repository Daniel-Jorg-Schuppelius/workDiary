<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : operations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => "Tâches d'exploitation",
        'subtitle' => 'Mises à jour, sauvegardes, expirations et pannes — priorisées et traçables.',
        'widget' => "Tâches d'exploitation ouvertes",
    ],
    'type' => [
        'backup_overdue' => 'Sauvegarde en retard',
        'backup_failed' => 'Échec de la sauvegarde',
        'restore_test_overdue' => 'Test de restauration en retard',
        'update_available' => 'Mise à jour disponible',
        'update_security' => 'Mise à jour de sécurité',
        'license_expiring' => 'Expiration de licence',
        'license_limit_near' => "Limite d’utilisateurs presque atteinte",
        'credential_expiring' => "Expiration d'identifiant/jeton",
        'connection_failing' => 'Panne de connexion',
        'component_eol' => 'Composant en fin de vie',
        'plugin_disabled' => 'Plugin désactivé',
        'scheduler_overdue' => 'Tâche planifiée en retard',
        'maintenance_scheduled' => 'Fenêtre de maintenance',
        'config_missing' => 'Configuration manquante',
        'support_grant_open' => 'Autorisation de support ouverte',
        'problem_report_open' => 'Signalement de problème ouvert',
        'cloud_intake_reauth' => 'Réception cloud : nouvelle connexion requise',
        'cloud_intake_quarantined' => 'Réception cloud : importations refusées',
    ],
    'severity' => [
        'info' => 'Information',
        'warning' => 'Avertissement',
        'critical' => 'Critique',
    ],
    'status' => [
        'open' => 'Ouverte',
        'snoozed' => 'Reportée',
        'delegated' => 'Déléguée',
        'ignored' => 'Ignorée',
        'done' => 'Terminée',
        'resolved' => "Résolue d'elle-même",
    ],
    'field' => [
        'task' => 'Tâche',
        'severity' => 'Gravité',
        'status' => 'Statut',
        'first_seen' => 'Détectée le',
        'last_seen' => 'Confirmée le',
        'assignee' => 'Responsable',
        'actions' => 'Actions',
        'note' => 'Justification',
        'snooze_until' => "Reporter jusqu'au",
        'system_wide' => "À l'échelle de l'installation",
    ],
    'action' => [
        'done' => 'Terminer',
        'snooze' => 'Reporter',
        'delegate' => 'Déléguer',
        'ignore' => 'Ignorer',
        'reopen' => 'Rouvrir',
        'open_link' => 'Vers la cause',
    ],
    'task' => [
        'backup_overdue' => 'La dernière sauvegarde date de :hours heures (seuil :threshold h).',
        'backup_failed' => 'Échec du contrôle de sauvegarde : :reason',
        'backup_target_failed' => 'Sauvegarde cloud échouée : :reason',
        'backup_target_verify_failed' => 'Vérification de la sauvegarde cloud échouée : :reason',
        'restore_test_overdue' => 'Le dernier test de restauration date de :days jours (seuil :threshold jours).',
        'restore_test_missing' => "Aucun test de restauration n'a jamais été consigné.",
        'update_available' => 'Mise à jour disponible pour :component : :installed → :available.',
        'update_security' => 'Mise à jour de sécurité pour :component : :installed → :available (:classification).',
        'license_expiring' => 'La licence expire le :date (:days jours restants).',
        'license_limit_near' => ':org : :current sur :max sièges sous licence utilisés — étendez la licence à temps.',
        'credential_expiring' => ':kind « :name » expire le :date.',
        'connection_failing' => 'Connexion « :name » (:kind) en panne : :error',
        'component_eol' => ':component :version n\'est plus pris en charge depuis le :date.',
        'plugin_disabled' => 'Le plugin « :plugin » a été désactivé automatiquement après :failures échecs.',
        'scheduler_overdue' => 'La tâche planifiée « :job » est en retard (échéance :due).',
        'maintenance_scheduled' => 'Fenêtre de maintenance :from – :to::scope',
        'support_grant_open' => 'Autorisation de support pour :grantee active jusqu\'au :until.',
        'problem_report_open' => 'Le signalement :reference de :name attend un traitement.',
        'problem_report_summary' => ':count signalement(s) ouvert(s) en attente de traitement.',
        'cloud_intake_reauth' => 'La réception de documents cloud :provider (« :folder ») doit être reconnectée (:status).',
        'cloud_intake_quarantined' => ':count fichier(s) de la réception de documents cloud ont été refusés (dernier motif : :reason).',
        'support_grant_summary' => ':count autorisation(s) de support active(s) — vérifier et révoquer si nécessaire.',
    ],
    'filter' => [
        'active' => 'Tâches actives',
        'all_severities' => 'Toutes les gravités',
        'all_types' => 'Tous les types',
    ],
    'empty' => [
        'title' => "Aucune tâche d'exploitation",
        'message' => "Rien à faire pour le moment — toutes les tâches d'exploitation sont terminées ou résolues d'elles-mêmes.",
    ],
    'hint' => [
        'auto_disabled_after' => 'Désactivé automatiquement après :failures tentatives échouées.',
        'no_contact_since' => 'Aucun contact depuis le :date.',
    ],
    'flash' => [
        'done' => 'Tâche marquée comme terminée.',
        'snoozed' => 'Tâche reportée jusqu\'au :date.',
        'delegated' => 'Tâche déléguée.',
        'ignored' => 'Tâche ignorée.',
        'reopened' => 'Tâche rouverte.',
    ],
    'widget' => [
        'open' => 'Tâches ouvertes',
        'empty' => "Aucune tâche d'exploitation ouverte.",
        'all' => "Afficher toutes les tâches d'exploitation",
    ],
];
