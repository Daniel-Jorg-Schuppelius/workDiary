<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanboard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Aufgaben ausgewählter Kanboard-Projekte als Aufgaben in WorkDiary-Projekte, nur lesend: Titel, Beschreibung, offen oder geschlossen und Fälligkeit. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Instanz',
    'task_link' => 'In Kanboard öffnen',
    'health' => [
        'not_configured' => 'Kanboard ist nicht eingerichtet (Adresse oder Zugang fehlen).',
        'not_connected' => 'Verbindung zu Kanboard noch nicht geprüft.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse der Kanboard-Instanz, z. B. https://kanban.example.com (ohne /jsonrpc.php).',
        'username' => 'Benutzername',
        'username_help' => 'Leer lassen für den Anwendungszugang (Benutzer „jsonrpc“ mit dem Token aus Einstellungen → API). Mit Benutzername gilt das persönliche Zugriffstoken dieses Benutzers und nur seine Projekte.',
        'api_token' => 'API-Token',
        'api_token_help' => 'Anwendungstoken aus Einstellungen → API oder persönliches Zugriffstoken des Benutzers.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
];
