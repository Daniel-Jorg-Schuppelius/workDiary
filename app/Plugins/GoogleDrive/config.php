<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Google-Drive-Plugin (Feature 080, MVP-355): LESENDER Cloud-Dokumenteingang
 * aus „Meine Ablage" und Shared Drives. OAuth2 Authorization-Code + PKCE mit
 * `access_type=offline` (Refresh-Token); schmaler Read-Only-Scope
 * `drive.readonly` (Metadaten + Inhalt; der noch schmalere `drive.file`
 * sieht nur app-erzeugte Dateien und taugt NICHT für den Import bestehender
 * Ordner). Google-native Docs/Sheets/Slides sind ausgeschlossen (kein
 * Binärinhalt ohne Export-Entscheidung).
 *
 * Delta: Erstabgleich über `files.list` (Seiten), danach `changes.list` ab
 * dem beim Start eingefrorenen `startPageToken`; der erneuerbare
 * changes-Watch-Channel ist nur Aufwecksignal.
 *
 * Client-ID/-Secret sind INSTALLATIONS-weit (ENV). Externe Hürde
 * (P10/Welle C): Google-OAuth-Verifikation (restricted scope!) — bis dahin
 * bleibt der produktive öffentliche Rollout sichtbar blockiert.
 */

return [
    'enabled' => env('GOOGLE_DRIVE_ENABLED', false),
    'client_id' => env('GOOGLE_DRIVE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET', ''),
    'api_base' => env('GOOGLE_DRIVE_API_BASE', 'https://www.googleapis.com/drive/v3'),
    'authorize_url' => env('GOOGLE_DRIVE_AUTHORIZE_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
    'token_url' => env('GOOGLE_DRIVE_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
    'scopes' => env('GOOGLE_DRIVE_SCOPES', 'https://www.googleapis.com/auth/drive.readonly'),
    'page_size' => (int) env('GOOGLE_DRIVE_PAGE_SIZE', 200),
    // Cloud-Backupziel (Feature 017 Phase 32, MVP-363): drive.file sieht NUR
    // app-erzeugte Dateien — reicht fürs Backup und ist der engste Scope.
    'backup_scopes' => env('GOOGLE_DRIVE_BACKUP_SCOPES', 'https://www.googleapis.com/auth/drive.file'),
    'upload_base' => env('GOOGLE_DRIVE_UPLOAD_BASE', 'https://www.googleapis.com/upload/drive/v3'),
];
