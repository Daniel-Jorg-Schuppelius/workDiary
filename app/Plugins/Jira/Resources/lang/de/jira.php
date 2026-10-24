<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : jira.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Vorgänge ausgewählter Jira-Cloud-Projekte als Aufgaben in WorkDiary-Projekte, nur lesend: Titel, Beschreibung, Status über die Statuskategorie und Fälligkeit. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Site',
    'task_link' => 'In Jira öffnen',
    'health' => [
        'not_configured' => 'Jira ist für diese Installation nicht konfiguriert (App-Registrierung des Herausgebers fehlt).',
        'not_connected' => 'Keine Jira-Verbindung mit gewählter Site.',
    ],
];
