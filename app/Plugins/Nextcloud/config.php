<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Nextcloud-Plugin (Feature 080 MVP-382 / Feature 017 Phase 32 MVP-383).
 *
 * Anders als Dropbox/Graph/Google gibt es KEINE installationsweiten App-Keys:
 * angebunden wird je Verbindung mit Server-URL, Nutzer und einem widerrufbaren
 * App-Passwort (Login Flow v2 als spätere Erweiterung). Dokumenteingang-
 * Verbindungen liegen pro Organisation in `cloud_document_connections`,
 * Backupziele systemweit in `backup_target_connections`.
 */

return [
    'enabled' => env('NEXTCLOUD_ENABLED', false),

    // Guzzle-Timeout je WebDAV-Request (Sekunden).
    'timeout' => (int) env('NEXTCLOUD_TIMEOUT', 30),

    // Rekursiver Scan (Import): Ordner je Delta-Seite (budgetierter Walk).
    'scan_folder_budget' => (int) env('NEXTCLOUD_SCAN_FOLDER_BUDGET', 50),

    // Obergrenze der pro Scan-Zyklus gemerkten fileids (Tombstone-Reconcile).
    // Darüber wird der Abgleich bewusst ausgelassen (Flag `overflow`) statt
    // still zu kürzen. Begrenzt zugleich die Checkpoint-Größe.
    'max_reconcile_files' => (int) env('NEXTCLOUD_MAX_RECONCILE_FILES', 5_000),

    // Chunk-Größe des resumable Backup-Uploads (10 MiB).
    'chunk_size' => (int) env('NEXTCLOUD_CHUNK_SIZE', 10_485_760),

    // On-Premise: interne Ziele (Loopback/RFC1918) nur nach ausdrücklicher
    // administrativer Freigabe. SaaS bleibt hart auf öffentlich routbare Ziele.
    'allow_private_targets' => (bool) env('NEXTCLOUD_ALLOW_PRIVATE_TARGETS', false),
];
