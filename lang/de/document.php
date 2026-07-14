<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : document.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Dokumente',
        'versions' => 'Versionen',
        'version_history' => 'Versionshistorie',
    ],

    'subtitle' => 'Verträge, Zertifikate, Prüfberichte und weitere Dokumente verwalten.',

    'field' => [
        'title' => 'Titel',
        'type' => 'Typ',
        'status' => 'Status',
        'reference' => 'Bezug',
        'validity' => 'Gültigkeit',
        'valid_from' => 'Gültig ab',
        'valid_until' => 'Gültig bis',
        'description' => 'Beschreibung',
        'file' => 'Datei',
        'version' => 'Version',
        'version_note' => 'Versionshinweis',
        'creator' => 'Erfasst von',
    ],

    'action' => [
        'create' => 'Dokument hinzufügen',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'delete' => 'Löschen',
        'archive' => 'Archivieren',
        'download' => 'Herunterladen',
        'add_version' => 'Neue Version hochladen',
    ],

    'filter' => [
        'all' => 'Alle',
        'search' => 'Suche',
        'search_placeholder' => 'Titel durchsuchen',
        'expiring' => 'Läuft ab',
        'expiring_days' => 'in :days Tagen',
    ],

    'ref' => [
        'customer' => 'Kunde',
        'project' => 'Projekt',
        'diary' => 'Auftrag',
        'asset' => 'Asset',
        'none' => 'Ohne Bezug',
    ],

    'badge' => [
        'current' => 'Aktuell',
        'expired' => 'Abgelaufen',
        'expires_soon' => 'Läuft bald ab',
    ],

    'flash' => [
        'created' => 'Dokument wurde angelegt.',
        'updated' => 'Dokument wurde aktualisiert.',
        'deleted' => 'Dokument wurde gelöscht.',
        'archived' => 'Dokument wurde archiviert.',
        'version_added' => 'Version :no wurde hochgeladen.',
    ],

    'error' => [
        'unknown_type' => 'Unbekannter Dokumenttyp.',
        'valid_until_before_from' => 'Das Ende der Gültigkeit muss nach deren Beginn liegen.',
    ],

    'hint' => [
        'upload' => 'Erlaubt: PDF, Bilder, Office-Dateien, Text/CSV, ZIP — max. 25 MB.',
    ],

    // Kundenfreigabe fürs Kundenportal (Welle D — Dokument-Spiegelung).
    'customer' => [
        'section' => 'Kundenfreigabe',
        'released' => 'Fürs Kundenportal freigegeben',
        'not_released' => 'Nicht freigegeben',
        'released_at' => 'Freigegeben am',
        'released_by' => 'Freigegeben von',
        'badge' => 'Portal',
        'not_linked_hint' => 'Nur kunden- oder auftragsgebundene Dokumente können freigegeben werden.',
        'action' => [
            'release' => 'Fürs Kundenportal freigeben',
            'revoke' => 'Freigabe zurückziehen',
        ],
        'confirm_revoke' => 'Freigabe fürs Kundenportal wirklich zurückziehen?',
        'flash' => [
            'released' => 'Dokument wurde fürs Kundenportal freigegeben.',
            'revoked' => 'Freigabe fürs Kundenportal wurde zurückgezogen.',
        ],
        'error' => [
            'not_linked' => 'Nur kunden- oder auftragsgebundene Dokumente können freigegeben werden.',
        ],
        'portal' => [
            'title' => 'Dokumente',
            'subtitle' => 'Die für Sie freigegebenen Dokumente.',
            'empty' => 'Es wurden noch keine Dokumente für Sie freigegeben.',
        ],
    ],

    'empty' => 'Noch keine Dokumente vorhanden.',
    'empty_title' => 'Keine Dokumente gefunden',
    'empty_filtered' => 'Für die aktuellen Filter wurden keine Dokumente gefunden.',
    'empty_versions' => 'Noch keine Versionen vorhanden.',
    'confirm_delete' => 'Dokument inkl. aller Versionen wirklich löschen?',
    'confirm_archive' => 'Dokument wirklich archivieren?',
];
