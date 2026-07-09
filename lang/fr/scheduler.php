<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scheduler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Tâches planifiées',
        'subtitle' => 'Suspendre, replanifier et surveiller les tâches du registre — sans modifier le code.',
        'help' => 'Uniquement des tâches enregistrées, uniquement des horaires autorisés',
        'help_text' => 'Toutes les tâches proviennent du registre côté serveur. La replanification est limitée aux intervalles autorisés par tâche ; les modifications sont auditées et prennent effet au prochain tick du planificateur.',
        'reschedule' => 'Replanifier la tâche',
    ],
    'field' => [
        'job' => 'Tâche',
        'plan' => 'Planification',
        'last_run' => 'Dernière exécution',
        'next_due' => 'Prochaine échéance',
        'failures' => 'Échecs consécutifs',
        'actions' => 'Actions',
        'cadence_type' => 'Intervalle',
        'time' => 'Heure',
        'day' => 'Jour',
        'expression' => 'Expression cron',
    ],
    'action' => [
        'reschedule' => 'Replanifier',
        'pause' => 'Suspendre',
        'resume' => 'Reprendre',
        'reset' => 'Rétablir la valeur par défaut',
        'test_run' => 'Lancer un test',
        'save' => 'Enregistrer',
    ],
    'state' => [
        'paused' => 'Suspendue',
        'success' => 'Réussie',
        'failed' => 'Échouée',
        'never_ran' => 'Jamais exécutée',
    ],
    'source' => [
        'default' => 'Plan par défaut',
        'setting' => 'Depuis un paramètre',
        'override' => 'Replanifiée manuellement',
    ],
    'cadence' => [
        'everyMinute' => 'Chaque minute',
        'everyFiveMinutes' => 'Toutes les 5 minutes',
        'everyFifteenMinutes' => 'Toutes les 15 minutes',
        'everyThirtyMinutes' => 'Toutes les 30 minutes',
        'hourly' => 'Toutes les heures',
        'dailyAt' => 'Chaque jour à',
        'weeklyOn' => 'Chaque semaine le',
        'monthlyOn' => 'Chaque mois le',
        'cron' => 'Expression cron',
    ],
    'criticality' => [
        'core' => 'Fonctionnement central',
        'integration' => 'Intégration',
        'housekeeping' => 'Nettoyage',
    ],
    'hint' => [
        'time' => 'Uniquement pour les plans quotidiens/hebdomadaires/mensuels.',
        'day' => 'Jour de semaine 0–6 (0 = dimanche) ou jour du mois 1–31.',
        'expression' => 'Réservé aux exploitants : minute heure jour mois jour-semaine.',
        'allowlist' => 'Durée prévue env. :runtime min. La tâche s\'exécute avec protection contre les chevauchements ; les intervalles trop courts sont refusés côté serveur.',
    ],
    'flash' => [
        'rescheduled' => 'La tâche :job a été replanifiée.',
        'paused' => 'La tâche :job a été suspendue.',
        'resumed' => 'La tâche :job a été reprise.',
        'reset' => 'La tâche :job utilise à nouveau le plan par défaut.',
        'test_run_queued' => 'Le test pour :job a été mis en file d\'attente.',
        'test_run_cooldown' => 'Veuillez patienter — un seul test par tâche toutes les :minutes minutes.',
    ],
];
