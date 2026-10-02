<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sharepoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'SharePoint-Ablage',
    'intro' => 'Freigegebene Dokumente werden nach Dokumenttyp über Microsoft Graph in eine SharePoint-Dokumentbibliothek gespiegelt — mit Übergabenachweis (Hash, Zeit, Ziel). WorkDiary bleibt führend; externe Änderungen an gespiegelten Dateien werden als Konflikt sichtbar, nie still übernommen.',
    'plugin_description' => 'Spiegelt freigegebene Dokumente über Microsoft Graph in eine SharePoint-Dokumentbibliothek — mit Übergabenachweis und Konfliktanzeige, ohne Rückkanal.',
    'not_configured_hint' => 'SHAREPOINT_CLIENT_ID/SECRET (bzw. die MSGRAPH_*-Fallback-Werte) sind nicht gesetzt — die Verbindung kann erst nach der App-Registrierung im Microsoft-Tenant hergestellt werden.',

    'health' => [
        'badge_ok' => 'Verbunden',
        'badge_failing' => 'Nicht erreichbar',
        'badge_inactive' => 'Inaktiv',
        'not_configured' => 'SharePoint ist nicht konfiguriert (SHAREPOINT_/MSGRAPH_CLIENT_ID/SECRET fehlen).',
        'no_org_context' => 'Konfiguriert (keine Organisation im Kontext).',
        'no_connection' => 'Keine SharePoint-Verbindung hergestellt.',
        'inactive' => 'SharePoint-Verbindung ist getrennt, pausiert oder ohne Ziel-Bibliothek.',
        'ok' => 'Verbunden — Ziel-Bibliothek erreichbar.',
        'failing' => 'Microsoft Graph nicht erreichbar oder Zugriff verweigert.',
        'error' => 'Microsoft-Graph-Fehler (:class).',
    ],

    'action' => [
        'connect' => 'Mit Microsoft 365 verbinden',
        'mirror' => 'Jetzt spiegeln',
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
    ],

    'target' => [
        'heading' => 'Ziel: Site + Dokumentbibliothek',
        'help' => 'Erst Site suchen, dann die Dokumentbibliothek wählen. Beides wird serverseitig über Microsoft Graph geprüft — bei Sites.Selected erscheinen nur freigegebene Sites.',
        'current' => 'Aktuelles Ziel',
        'search' => 'Site suchen',
        'search_placeholder' => 'Name oder Stichwort der Site',
        'search_action' => 'Suchen',
        'no_sites' => 'Keine Sites gefunden (Suchbegriff prüfen; bei Sites.Selected muss der Tenant-Admin die Site freigeben).',
        'selected' => 'Gewählt',
        'drive' => 'Dokumentbibliothek',
        'no_drives' => 'Keine Dokumentbibliotheken in dieser Site gefunden.',
    ],

    'settings' => [
        'heading' => 'Ordnerregeln + Quellen',
    ],

    'field' => [
        'default_folder' => 'Standardordner',
        'active' => 'Aktiv',
        'sources' => 'Gespiegelte Inhalte',
        'source_document' => 'Dokumente (DMS)',
        'source_invoice_pdf' => 'Rechnungen (PDF)',
        'source_protocol_pdf' => 'Protokolle (PDF)',
        'sources_help' => 'Welche Inhalte in diese Bibliothek gespiegelt werden. Ohne Auswahl nur freigegebene Dokumente.',
    ],

    'folder' => [
        'heading' => 'Dokumenttyp → Ordner',
        'help' => 'Ordnet Dokumenttypen einem Unterordner (relativ zur Bibliothek) zu. Ohne Treffer greift der Standardordner.',
        'type_placeholder' => '— Dokumenttyp —',
        'path_placeholder' => 'Unterordner',
    ],

    'conflict' => [
        'subtitle' => 'Externe Änderung erkannt — Spiegelung angehalten (kein Überschreiben).',
        'action' => [
            'overwrite' => 'Remote überschreiben',
            'import' => 'Als neue Version importieren',
            'detach' => 'Spiegelung trennen',
        ],
        'confirm' => [
            'overwrite' => 'Die externe Datei mit dem lokalen Stand überschreiben? Die externe Änderung geht verloren.',
            'import' => 'Den externen Stand als neue lokale Version übernehmen?',
            'detach' => 'Die Spiegelung dieses Dokuments dauerhaft trennen? Die Anbindung bleibt aktiv.',
        ],
        'flash' => [
            'overwritten' => 'Externe Datei mit dem lokalen Stand überschrieben.',
            'imported' => 'Externer Stand als neue lokale Version importiert.',
            'detached' => 'Spiegelung dieses Dokuments getrennt.',
            'failed' => 'Konfliktauflösung fehlgeschlagen: :reason',
        ],
        'import_note' => 'Aus SharePoint importiert (Konfliktauflösung).',
    ],

    'flash' => [
        'not_configured' => 'SharePoint ist nicht konfiguriert (Client-ID/Secret fehlen).',
        'state_invalid' => 'Der OAuth-Vorgang ist abgelaufen oder ungültig — bitte erneut verbinden.',
        'oauth_denied' => 'Microsoft hat keinen Autorisierungscode geliefert (Vorgang abgebrochen?).',
        'oauth_failed' => 'Token-Austausch fehlgeschlagen (:class).',
        'connected' => 'Mit Microsoft 365 verbunden. Jetzt Site + Bibliothek wählen.',
        'disconnected' => 'SharePoint-Verbindung getrennt. Bereits gespiegelte Dateien bleiben extern erhalten.',
        'no_connection' => 'Keine aktive SharePoint-Verbindung vorhanden.',
        'site_invalid' => 'Die gewählte Site ist nicht erreichbar oder nicht freigegeben.',
        'drive_invalid' => 'Die gewählte Dokumentbibliothek gehört nicht zur gewählten Site.',
        'target_saved' => 'Ziel-Bibliothek gespeichert.',
        'saved' => 'SharePoint-Einstellungen gespeichert.',
        'mirror_done' => 'Spiegellauf gestartet.',
    ],
];
