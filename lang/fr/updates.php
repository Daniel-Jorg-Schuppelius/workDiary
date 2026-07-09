<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : updates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => ['section' => 'Mises à jour disponibles'],
    'field' => [
        'mode' => 'Mode de vérification',
        'last_checked' => 'Dernière vérification',
        'component' => 'Composant',
        'versions' => 'Installée → Disponible',
        'classification' => 'Classification',
        'requirements' => 'Préparation',
        'incompatible' => 'Incompatible avec cette version de l\'application',
        'changelog' => 'Journal des modifications',
    ],
    'classification' => [
        'normal' => 'Routine',
        'recommended' => 'Recommandée',
        'security' => 'Sécurité',
        'critical' => 'Critique',
    ],
    'requires' => [
        'backup' => 'Sauvegarde requise',
        'maintenance_window' => 'Fenêtre de maintenance recommandée',
        'migrations' => 'Migrations de base de données',
    ],
    'action' => [
        'check_now' => 'Vérifier maintenant',
        'import' => 'Import hors ligne',
        'snooze' => 'Reporter',
        'acknowledge' => 'Mettre en sourdine',
    ],
    'empty' => 'Aucune mise à jour en attente connue.',
    'flash' => [
        'checked' => 'Vérification terminée — :count mise(s) à jour en attente.',
        'imported' => 'Document importé — :count mise(s) à jour en attente.',
        'snoozed' => 'Avis de mise à jour reporté.',
        'acknowledged' => 'Avis mis en sourdine (reste visible ici).',
    ],
];
