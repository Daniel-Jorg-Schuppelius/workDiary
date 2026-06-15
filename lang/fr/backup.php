<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'status' => 'Sauvegarde & restauration',
        'log_restore_test' => 'Consigner un test de restauration',
    ],

    'subtitle' => 'État des sauvegardes externes par source, alertes de fraîcheur et registre des tests de restauration réalisés.',

    'section' => [
        'last_per_source' => 'Dernière sauvegarde par source',
        'restore_register' => 'Registre des tests de restauration',
        'restore_test' => 'Test de restauration',
        'retention' => 'Rétention',
    ],

    'field' => [
        'source' => 'Source',
        'occurred_at' => 'Horodatage',
        'age' => 'Ancienneté',
        'size' => 'Taille',
        'manifest_hash' => 'Empreinte du manifeste',
        'state' => 'État',
        'tested_on' => 'Testé le',
        'result' => 'Résultat',
        'scope' => 'Périmètre',
        'restored_size' => 'Restauré',
        'restored_size_bytes' => 'Taille restaurée (octets)',
        'duration' => 'Durée',
        'duration_minutes' => 'Durée (minutes)',
        'next_due' => 'Prochaine échéance',
        'performed_by' => 'Réalisé par',
        'notes' => 'Note',
        'last_passed' => 'Dernier test réussi',
        'no_passed_test' => 'Aucun test de restauration réussi consigné',
    ],

    'badge' => [
        'fresh' => 'à jour',
        'overdue' => 'en retard',
    ],

    'value' => [
        'hours' => ':n h',
        'minutes' => ':n min',
        'days_ago' => 'il y a :n jours',
    ],

    'action' => [
        'log_restore_test' => 'Consigner un test de restauration',
        'save' => 'Enregistrer',
        'open_help' => 'Ouvrir le manuel des sauvegardes',
    ],

    'warn' => [
        'no_heartbeat_title' => 'Aucune sauvegarde enregistrée',
        'no_heartbeat_body' => "Aucun signal de sauvegarde (heartbeat) n'a encore été reçu. Vérifiez que le script de sauvegarde externe s'exécute et appelle le point d'accès heartbeat avec un jeton valide.",
        'overdue_title' => 'Sauvegarde en retard',
        'overdue_body' => "Au moins une source n'a pas signalé de heartbeat depuis plus de :hours heures. Vérifiez la dernière sauvegarde.",
        'restore_overdue_title' => 'Test de restauration en retard',
        'restore_overdue_body' => 'Aucun test de restauration réussi consigné depuis plus de :days jours. Veuillez réaliser un test de restauration et le consigner ici.',
    ],

    'hint' => [
        'freshness' => 'Une source est en retard si son dernier heartbeat date de plus de :hours heures (configurable via BACKUP_HEARTBEAT_FRESHNESS_HOURS).',
        'register_manual' => "Ceci est un registre traçable. La restauration réelle est effectuée manuellement ou par script en dehors de WorkDiary — l'exécution automatisée de la restauration ne fait délibérément pas partie de cette page.",
        'retention' => 'Rétention recommandée : 7 quotidiennes, 4 hebdomadaires, 12 mensuelles (règle 3-2-1). Au moins une sauvegarde hors site sur un autre emplacement.',
        'see_docs' => 'Les détails sur la stratégie, le heartbeat et la restauration pas à pas figurent dans docs/backup-restore.md.',
    ],

    'empty' => [
        'no_heartbeat' => 'Aucune sauvegarde enregistrée',
        'no_heartbeat_hint' => "Dès que le script de sauvegarde externe envoie un heartbeat, la dernière sauvegarde par source apparaît ici.",
        'no_restore_tests' => 'Aucun test de restauration consigné',
    ],

    'placeholder' => [
        'source' => 'p. ex. nightly, offsite, weekly-full',
        'scope' => 'p. ex. BD+stockage, pièces jointes uniquement',
        'notes' => 'Observations, réserves, écarts …',
    ],

    'flash' => [
        'restore_test_logged' => 'Test de restauration consigné.',
    ],

    'generated_at' => 'État au : :at',
];
