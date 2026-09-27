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
    'title' => 'Informe de seguridad y privacidad :version',
    'generated' => 'Generado el :date.',
    'none' => 'sin entradas',
    'no_advisories' => 'sin avisos de seguridad abiertos',
    'no_integrity' => 'aún sin comprobación de integridad',
    'integrity' => 'Estado :status el :date, :files archivos, :findings desviaciones',
    'components' => ':count componentes en el SBOM',
    'section' => [
        'privacy' => 'Cambios relevantes para la privacidad',
        'advisories' => 'Avisos de seguridad abiertos',
        'integrity' => 'Integridad del código fuente',
        'dependencies' => 'Dependencias',
        'changes' => 'Todos los cambios',
    ],
];
