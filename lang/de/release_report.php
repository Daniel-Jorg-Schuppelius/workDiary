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
    'title' => 'Sicherheits- und Datenschutzbericht :version',
    'generated' => 'Erstellt am :date.',
    'none' => 'keine Einträge',
    'no_advisories' => 'keine offenen Sicherheitshinweise',
    'no_integrity' => 'noch keine Integritätsprüfung',
    'integrity' => 'Status :status am :date, :files Dateien, :findings Abweichungen',
    'components' => ':count Komponenten in der SBOM',
    'section' => [
        'privacy' => 'Datenschutzrelevante Änderungen',
        'advisories' => 'Offene Sicherheitshinweise',
        'integrity' => 'Quelltext-Integrität',
        'dependencies' => 'Abhängigkeiten',
        'changes' => 'Alle Änderungen',
    ],
];
