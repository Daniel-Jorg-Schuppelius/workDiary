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
    'title' => 'Nutzung je Mandant',
    'subtitle' => 'Nutzer, Speicher, Module und letzte Aktivität je Organisation — nur für den Plattformbetrieb.',
    'back' => 'Organisationen',
    'empty' => 'Keine Organisationen vorhanden.',
    'field' => [
        'organization' => 'Organisation',
        'status' => 'Status',
        'users' => 'Nutzer',
        'active_users' => 'Aktiv (30 Tage)',
        'storage' => 'Speicher',
        'modules' => 'Module',
        'last_activity' => 'Letzte Aktivität',
    ],
    'benchmark' => [
        'link' => 'Branchenvergleich',
        'subtitle' => 'Jahresemissionen je Hauptbranchenprofil über alle Mandanten ohne Demo, nur ab drei Organisationen je Branche.',
        'title' => 'Emissionen je Branche :year (anonym)',
        'branch' => 'Branche',
        'organizations' => 'Organisationen',
        'mean' => 'Mittelwert',
        'median' => 'Median',
        'empty' => 'Keine Branche mit mindestens :min Organisationen und erfassten Emissionen.',
    ],
];
