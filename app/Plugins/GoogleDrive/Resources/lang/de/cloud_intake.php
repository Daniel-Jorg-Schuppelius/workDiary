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
        'connect_google' => 'Google Drive verbinden',
    ],
    'google' => [
        'description' => 'Übernimmt Dokumente lesend aus überwachten Google-Drive-Ordnern (Cloud-Dokumenteingang) — Meine Ablage und Shared Drives; Rollout bis zur Google-OAuth-Verifikation blockiert.',
        'health' => [
            'not_configured' => 'Google-Drive-Client-Schlüssel nicht konfiguriert.',
            'no_org_context' => 'Kein Organisationskontext (Systemlauf).',
            'attention' => 'Mindestens eine Google-Drive-Verbindung braucht Aufmerksamkeit (Re-Auth/blockiert).',
            'backup_attention' => 'Das Google-Drive-Backupziel braucht Aufmerksamkeit (Re-Auth/blockiert) — betrifft alle Organisationen.',
            'ok' => 'Google-Drive-Verbindungen in Ordnung.',
            'error' => 'Health-Prüfung fehlgeschlagen (:class).',
        ],
    ],
];
