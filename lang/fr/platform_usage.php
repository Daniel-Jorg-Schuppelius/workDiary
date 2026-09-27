<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : platform_usage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nutzung je Mandant und Branchenvergleich (MVP-951/949).
return [
    'title' => 'Utilisation par client',
    'subtitle' => 'Utilisateurs, stockage, modules et dernière activité par organisation — réservé à l’exploitation de la plateforme.',
    'back' => 'Organisations',
    'empty' => 'Aucune organisation.',
    'field' => [
        'organization' => 'Organisation',
        'status' => 'Statut',
        'users' => 'Utilisateurs',
        'active_users' => 'Actifs (30 jours)',
        'storage' => 'Stockage',
        'modules' => 'Modules',
        'last_activity' => 'Dernière activité',
    ],
    'benchmark' => [
        'link' => 'Comparaison sectorielle',
        'subtitle' => 'Émissions annuelles par profil sectoriel principal pour tous les clients hors démo, uniquement à partir de trois organisations par secteur.',
        'title' => 'Émissions par secteur :year (anonyme)',
        'branch' => 'Secteur',
        'organizations' => 'Organisations',
        'mean' => 'Moyenne',
        'median' => 'Médiane',
        'empty' => 'Aucun secteur comptant au moins :min organisations avec des émissions saisies.',
    ],
];
