<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vikunja.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Aufgaben ausgewählter Vikunja-Projekte als Aufgaben in WorkDiary-Projekte, nur lesend: Titel, Beschreibung, erledigt und Fälligkeit. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Instanz',
    'task_link' => 'In Vikunja öffnen',
    'health' => [
        'not_configured' => 'Vikunja ist nicht eingerichtet (Adresse oder Zugang fehlen).',
        'not_connected' => 'Verbindung zu Vikunja noch nicht geprüft.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse der Vikunja-Instanz, z. B. https://todo.example.com (ohne /api/v1).',
        'api_token' => 'API-Token',
        'api_token_help' => 'In Vikunja unter Einstellungen → API-Tokens anlegen, mit Leserecht auf Projekte und Aufgaben. Erfordert Vikunja ab 1.0; mit dem Recht „other → user“ zeigt die Prüfung auch den Kontonamen.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
];
