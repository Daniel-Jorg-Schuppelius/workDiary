<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'action' => [
        'push' => 'An Buchhaltung übertragen',
    ],

    'flash' => [
        'pushed' => 'Kunde an die Buchhaltung übertragen (ID :id).',
        'failed' => 'Übertragung fehlgeschlagen: :msg',
        'no_plugin' => 'Kein Buchhaltungssystem aktiv.',
    ],

    'error' => [
        'accounting_leads' => 'Die Buchhaltung führt die Stammdaten — es wird nicht übertragen (Einstellung „Stammdaten-Hoheit").',
        'no_syncer' => 'Das Plugin :plugin überträgt keine Kontakte.',
    ],

    'authority' => [
        'workdiary' => 'workDiary führt',
        'accounting' => 'Buchhaltung führt',
    ],
];
