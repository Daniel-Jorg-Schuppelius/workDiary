<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : leantime.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt To-dos ausgewählter Leantime-Projekte als Aufgaben in WorkDiary-Projekte, nur lesend: Titel, Beschreibung, Status nach Statusart und Fälligkeit; Meilensteine bleiben außen vor. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Instanz',
    'task_link' => 'In Leantime öffnen',
    'health' => [
        'not_configured' => 'Leantime ist nicht eingerichtet (Adresse oder Zugang fehlen).',
        'not_connected' => 'Verbindung zu Leantime noch nicht geprüft.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse der Leantime-Instanz, z. B. https://leantime.example.com (ohne /api/jsonrpc).',
        'api_key' => 'API-Schlüssel',
        'api_key_help' => 'In Leantime unter Firmeneinstellungen → API anlegen und den Projekten zuordnen, die übernommen werden sollen; der Schlüssel wird nur einmal angezeigt. Erfordert Leantime ab 3.8.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
];
