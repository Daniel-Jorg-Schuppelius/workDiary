<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asana.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Aufgaben ausgewählter Asana-Projekte in WorkDiary-Projekte, nur lesend: Titel, Notizen, Erledigt-Status und Fälligkeit. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Workspace',
    'task_link' => 'In Asana öffnen',
    'health' => [
        'not_configured' => 'Asana ist für diese Installation nicht konfiguriert (App-Registrierung des Herausgebers fehlt).',
        'not_connected' => 'Keine Asana-Verbindung mit gewähltem Workspace.',
    ],
];
