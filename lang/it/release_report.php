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
    'title' => 'Rapporto sicurezza e privacy :version',
    'generated' => 'Generato il :date.',
    'none' => 'nessuna voce',
    'no_advisories' => 'nessun avviso di sicurezza aperto',
    'no_integrity' => 'nessun controllo di integrità',
    'integrity' => 'Stato :status il :date, :files file, :findings scostamenti',
    'components' => ':count componenti nel SBOM',
    'section' => [
        'privacy' => 'Modifiche rilevanti per la privacy',
        'advisories' => 'Avvisi di sicurezza aperti',
        'integrity' => 'Integrità del codice sorgente',
        'dependencies' => 'Dipendenze',
        'changes' => 'Tutte le modifiche',
    ],
];
