<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metrics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => "Métriques d'exploitation",
    ],

    'subtitle' => "Indicateurs techniques et utilisation agrégée des fonctionnalités de cette installation.",

    'privacy_notice' => "Toutes les métriques sont collectées et stockées exclusivement en local. Aucun envoi externe n'a lieu ; l'utilisation des fonctionnalités est comptée uniquement comme agrégat journalier par organisation — sans référence personnelle et sans contenu métier.",

    'section' => [
        'queue' => 'File d\'attente',
        'backups' => 'Heartbeats de sauvegarde',
        'plugin_errors' => 'Erreurs de plugin (7 jours)',
        'storage' => 'Stockage',
        'active_users' => 'Utilisateurs actifs (30 jours)',
        'module_counts' => 'Enregistrements par module principal',
        'feature_usage' => 'Utilisation des fonctionnalités (30 jours)',
    ],

    'field' => [
        'version' => 'Version',
        'queue_pending' => 'Tâches en attente',
        'queue_failed' => 'Tâches échouées',
        'attachments' => 'Pièces jointes',
        'document_versions' => 'Versions de documents',
        'feature' => 'Fonctionnalité',
        'usage_total' => 'Nombre',
        'last_used_on' => 'Dernière utilisation',
    ],

    'module' => [
        'diary_entries' => 'Missions (journal)',
        'protocols' => 'Protocoles',
        'documents' => 'Documents',
        'form_submissions' => 'Formulaires (remplis)',
        'knowledge_articles' => 'Articles de connaissance',
        'communication_notes' => 'Notes de communication',
    ],

    'empty' => [
        'queue' => 'Aucune table de file d\'attente disponible (driver sync).',
        'backups' => 'Aucun heartbeat de sauvegarde reçu pour le moment.',
        'plugin_errors' => 'Aucune erreur de plugin au cours des 7 derniers jours.',
        'active_users' => 'Aucune donnée de connexion disponible.',
        'feature_usage' => 'Aucune utilisation de fonctionnalité enregistrée pour le moment.',
    ],

    'hint' => [
        'storage_db_metadata' => 'Nombre et taille selon les métadonnées de la base de données (pas de scan du système de fichiers — l\'occupation disque est affichée sur la page de diagnostics).',
        'active_users' => 'Utilisateurs distincts avec une connexion au cours des 30 derniers jours (source : journal d\'audit).',
        'feature_usage_window' => 'Agrégé par organisation et par jour sur les 30 derniers jours. Les données restent locales.',
    ],

    'generated_at' => 'Généré : :at',
];
