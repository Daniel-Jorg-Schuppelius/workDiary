<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gitea.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Issues ausgewählter Repositories einer selbst gehosteten Gitea- oder Forgejo-Instanz als Aufgaben in WorkDiary-Projekte, nur lesend: Titel, Beschreibung, offen/geschlossen und Fälligkeit. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Instanz',
    'task_link' => 'In Gitea/Forgejo öffnen',
    'health' => [
        'not_configured' => 'Gitea/Forgejo ist nicht eingerichtet (Basis-URL oder Token fehlt).',
        'not_connected' => 'Verbindung zu Gitea/Forgejo noch nicht geprüft.',
    ],
    'settings' => [
        'base_url' => 'Basis-URL',
        'base_url_help' => 'Adresse der Gitea- oder Forgejo-Instanz, z. B. https://git.example.com (ohne /api/v1).',
        'api_token' => 'Access Token',
        'api_token_help' => 'In Gitea/Forgejo unter Einstellungen → Anwendungen erzeugen, mit Lesezugriff auf Repositories und Issues. Nötig sind die Bereiche read:user, read:repository und read:issue.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Basis-URL mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
];
