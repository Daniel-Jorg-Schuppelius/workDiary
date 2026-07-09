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
    'title' => ['section' => 'Verfügbare Updates'],
    'field' => [
        'mode' => 'Prüfmodus',
        'last_checked' => 'Letzte Prüfung',
        'component' => 'Komponente',
        'versions' => 'Installiert → Verfügbar',
        'classification' => 'Einstufung',
        'requirements' => 'Vorbereitung',
        'incompatible' => 'Inkompatibel mit dieser App-Version',
        'changelog' => 'Änderungen',
    ],
    'classification' => [
        'normal' => 'Routine',
        'recommended' => 'Empfohlen',
        'security' => 'Sicherheit',
        'critical' => 'Kritisch',
    ],
    'requires' => [
        'backup' => 'Backup erforderlich',
        'maintenance_window' => 'Wartungsfenster empfohlen',
        'migrations' => 'Datenbank-Migrationen',
    ],
    'action' => [
        'check_now' => 'Jetzt prüfen',
        'import' => 'Offline-Import',
        'snooze' => 'Zurückstellen',
        'acknowledge' => 'Stummschalten',
    ],
    'empty' => 'Keine offenen Updates bekannt.',
    'flash' => [
        'checked' => 'Update-Prüfung abgeschlossen — :count offene(s) Update(s).',
        'imported' => 'Update-Dokument importiert — :count offene(s) Update(s).',
        'snoozed' => 'Update-Hinweis zurückgestellt.',
        'acknowledged' => 'Update-Hinweis stummgeschaltet (bleibt hier sichtbar).',
    ],
];
