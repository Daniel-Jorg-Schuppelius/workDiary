<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : paperless.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Übernimmt Dokumente aus einer selbst gehosteten Paperless-ngx-Instanz in den Cloud-Dokumenteingang, nur lesend: wahlweise alle Dokumente oder die eines Tags, Korrespondenten oder Dokumenttyps. Die Regeln des Eingangs leiten sie in den Rechnungseingang oder die Dokumentablage.',
    ],
    'settings' => [
        'base_url' => 'Basis-URL',
        'base_url_help' => 'Adresse der Paperless-ngx-Instanz, z. B. https://paperless.example.com (ohne /api).',
        'api_token' => 'API-Token',
        'api_token_help' => 'In Paperless-ngx unter „Mein Profil“ erzeugen. Der Token liest mit den Rechten seines Benutzers; WorkDiary ändert in Paperless nichts. Nötig ist Leserecht auf Dokumente, Tags, Korrespondenten, Dokumenttypen und „UISettings“; sichtbar sind nur Dokumente, die der Benutzer sehen darf.',
        'allow_private_network' => 'Private Adressen erlauben',
        'allow_private_network_help' => 'Nur für eine Instanz im eigenen Netz: erlaubt eine Basis-URL mit privater oder interner Adresse. Wird protokolliert und wirkt nur, wenn der Betreiber diese Freigabe zulässt.',
    ],
    'action' => [
        'connect' => 'Paperless-ngx verbinden',
    ],
    'connect' => [
        'title' => 'Paperless-ngx verbinden',
        'legend' => 'Verbindung',
        'instance' => 'Instanz aus den Plugin-Einstellungen: :instance',
        'name' => 'Name der Verbindung',
        'hint' => 'Danach wählen Sie die Quelle (alle Dokumente, Tag, Korrespondent oder Dokumenttyp), als Stammordner „/“ oder einen Dokumenttyp, und legen Regeln an. Der Pfad eines Dokuments lautet Dokumenttyp/Korrespondent/Dateiname.',
        'submit' => 'Verbindung prüfen',
        'not_configured' => 'Hinterlegen Sie zuerst Basis-URL und API-Token in den Plugin-Einstellungen.',
        'to_settings' => 'Zu den Plugin-Einstellungen',
    ],
    'container' => [
        'all' => 'Alle Dokumente',
        'kind' => [
            'tag' => 'Tag',
            'correspondent' => 'Korrespondent',
            'type' => 'Dokumenttyp',
        ],
    ],
    'flash' => [
        'not_configured' => 'Paperless-ngx ist nicht eingerichtet: Basis-URL oder API-Token fehlt in den Plugin-Einstellungen.',
        'instance_changed' => 'Die Plugin-Einstellungen zeigen auf eine andere Paperless-Instanz als diese Verbindung. Trennen Sie die Verbindung zuerst; bereits übernommene Belege bleiben erhalten.',
    ],
    'health' => [
        'not_configured' => 'Paperless-ngx ist nicht eingerichtet (Basis-URL oder Token fehlt).',
        'not_connected' => 'Noch keine Paperless-Verbindung im Cloud-Dokumenteingang.',
        'attention' => 'Die Paperless-Verbindung meldet einen Fehler — siehe Cloud-Dokumenteingang.',
    ],
];
