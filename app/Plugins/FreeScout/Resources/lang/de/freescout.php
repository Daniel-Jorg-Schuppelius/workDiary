<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : freescout.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Konversationen ausgewählter FreeScout-Postfächer als Aufgaben in WorkDiary-Projekte, nur lesend: Betreff, erste Nachricht als Beschreibung und Status. Konflikte und Klärfälle landen in der Integrations-Inbox.',
    ],
    'source' => 'Instanz',
    'task_link' => 'In FreeScout öffnen',
    'health' => [
        'not_configured' => 'FreeScout ist nicht eingerichtet (Adresse oder Zugang fehlen).',
        'not_connected' => 'Verbindung zu FreeScout noch nicht geprüft.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse der FreeScout-Instanz, z. B. https://support.example.com (ohne /api). Das Modul „API & Webhooks“ muss aktiv sein.',
        'api_key' => 'API-Schlüssel',
        'api_key_help' => 'In FreeScout unter Verwalten → API & Webhooks erzeugen. Der Schlüssel gilt für die ganze Instanz.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Adresse mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
];
