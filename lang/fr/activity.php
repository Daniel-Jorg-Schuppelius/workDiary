<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : activity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Aktivitäts-Feed (Feature 183).
return [
    'title' => 'Activité',
    'subtitle' => 'Qui a modifié quoi et quand · :range',
    'subject_title' => 'Historique des modifications',
    'more' => 'Les entrées plus anciennes figurent dans le journal d’audit.',
    'more_in_audit' => 'Entrées plus anciennes dans le journal d’audit',
    'newer' => 'Plus récentes',
    'older' => 'Plus anciennes',
    'filter' => [
        'type' => 'Type d’objet',
        'all_types' => 'Tous les types d’objet',
        'user' => 'Personne',
        'all_users' => 'Toutes les personnes',
    ],
];
