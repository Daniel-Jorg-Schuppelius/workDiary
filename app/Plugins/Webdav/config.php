<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * WebDAV-Plugin (Feature 058, MVP-127). Die Ablage (Collection-URL,
 * Zugangsdaten verschlüsselt, Ordnerregeln) liegt PRO ORGANISATION in
 * `webdav_connections` und wird über das Admin-Panel gepflegt. ENV dient nur als
 * globaler Aktivierungs-Fallback für Tests/Konsole.
 *
 * Zustellung über die generische Integrations-Outbox (Idempotenz, Retry,
 * Konflikt-in-Inbox); Protokoll: WebDAV über HTTP, Basic-Auth (App-Passwort).
 */

return [
    'enabled' => env('WEBDAV_ENABLED', false),
    // Backupziel (Feature 123, MVP-612): Ohne ausdrückliche Freigabe nur
    // öffentlich routbare HTTPS-Ziele (SSRF/DNS-Rebinding). On-Premise-
    // Installationen dürfen den eigenen Server im internen Netz freigeben.
    'allow_private_targets' => env('WEBDAV_ALLOW_PRIVATE_TARGETS', false),
    // Fortsetzbarer Upload (MVP-721): Backup-Teile über dieser Größe gehen in
    // Content-Range-PUTs dieser Größe und laufen nach einem Abbruch ab dem
    // vorhandenen Byte weiter; 0 = immer ein einzelner PUT. Server ohne
    // Teil-PUT (Nextcloud/SabreDAV) fallen automatisch zurück.
    'backup_chunk_bytes' => (int) env('WEBDAV_BACKUP_CHUNK_BYTES', 16 * 1024 * 1024),
];
