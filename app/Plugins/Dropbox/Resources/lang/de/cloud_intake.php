<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'action' => [
        'connect_dropbox' => 'Dropbox verbinden',
    ],
    'dropbox' => [
        'description' => 'Übernimmt Dokumente lesend aus überwachten Dropbox-Ordnern (Cloud-Dokumenteingang) — mit Ordnerregeln, Übergabenachweis und Inbox für unklare Fälle.',
        'health' => [
            'not_configured' => 'Dropbox-App-Schlüssel nicht konfiguriert.',
            'no_org_context' => 'Kein Organisationskontext (Systemlauf).',
            'attention' => 'Mindestens eine Dropbox-Verbindung braucht Aufmerksamkeit (Re-Auth/blockiert).',
            'backup_attention' => 'Das Dropbox-Backupziel braucht Aufmerksamkeit (Re-Auth/blockiert) — betrifft alle Organisationen.',
            'ok' => 'Dropbox-Verbindungen in Ordnung.',
            'error' => 'Health-Prüfung fehlgeschlagen (:class).',
        ],
    ],
];
