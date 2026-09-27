<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : release_report.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sicherheits- und Datenschutzbericht je Release (MVP-946).
return [
    'title' => 'Rapport de sécurité et de protection des données :version',
    'generated' => 'Généré le :date.',
    'none' => 'aucune entrée',
    'no_advisories' => 'aucun avis de sécurité ouvert',
    'no_integrity' => 'aucun contrôle d\'intégrité',
    'integrity' => 'Statut :status le :date, :files fichiers, :findings écarts',
    'components' => ':count composants dans le SBOM',
    'section' => [
        'privacy' => 'Modifications relatives à la protection des données',
        'advisories' => 'Avis de sécurité ouverts',
        'integrity' => 'Intégrité du code source',
        'dependencies' => 'Dépendances',
        'changes' => 'Toutes les modifications',
    ],
];
