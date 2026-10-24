<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : glpi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Tickets ausgewählter GLPI-Entitäten als Aufgaben in WorkDiary-Projekte, nur lesend: Titel, Beschreibung, Status und Lösungsfrist. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Instanz',
    'task_link' => 'In GLPI öffnen',
    'health' => [
        'not_configured' => 'GLPI ist nicht eingerichtet (Adresse oder Zugang fehlen).',
        'not_connected' => 'Verbindung zu GLPI noch nicht geprüft.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse der GLPI-Instanz, z. B. https://glpi.example.com (ohne /apirest.php). Die REST-API muss in GLPI unter Einrichtung → Allgemein → API aktiv sein.',
        'app_token' => 'App-Token',
        'app_token_help' => 'Nur nötig, wenn der API-Client in GLPI ein App-Token verlangt.',
        'user_token' => 'Benutzer-Token',
        'user_token_help' => 'In GLPI in den Einstellungen des Benutzers unter „Fernzugriffsschlüssel“ (API-Token) erzeugen. Sichtbar sind die Entitäten und Tickets dieses Benutzers.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
];
