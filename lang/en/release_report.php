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
    'title' => 'Security and privacy report :version',
    'generated' => 'Generated on :date.',
    'none' => 'no entries',
    'no_advisories' => 'no open security advisories',
    'no_integrity' => 'no integrity check yet',
    'integrity' => 'Status :status on :date, :files files, :findings deviations',
    'components' => ':count components in the SBOM',
    'section' => [
        'privacy' => 'Privacy-relevant changes',
        'advisories' => 'Open security advisories',
        'integrity' => 'Source integrity',
        'dependencies' => 'Dependencies',
        'changes' => 'All changes',
    ],
];
