<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plane.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Arbeitspakete ausgewählter Plane-Projekte als Aufgaben in WorkDiary-Projekte, nur lesend: Titel, Beschreibung, Status nach Zustandsgruppe und Zieldatum. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Workspace',
    'task_link' => 'In Plane öffnen',
    'health' => [
        'not_configured' => 'Plane ist nicht eingerichtet (Adresse oder Zugang fehlen).',
        'not_connected' => 'Verbindung zu Plane noch nicht geprüft.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse der API: für Plane Cloud https://api.plane.so, sonst die Adresse der eigenen Instanz (ohne /api/v1).',
        'workspace_slug' => 'Workspace-Kürzel',
        'workspace_slug_help' => 'Der Teil der Adresse nach dem Host, z. B. „acme“ in https://app.plane.so/acme/.',
        'api_key' => 'API-Schlüssel',
        'api_key_help' => 'In Plane unter Profileinstellungen → Persönliche Zugriffstoken anlegen; er sieht die Projekte, in denen das Profil Mitglied ist. Erfordert Plane ab 0.28.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
];
