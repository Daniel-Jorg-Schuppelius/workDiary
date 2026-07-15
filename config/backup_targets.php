<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Verschlüsselte Cloud-Backupziele (Feature 017, Phase 32, MVP-361–366).
 *
 * Schlüssel: BACKUP_MASTER_KEY ist der EINZIGE reguläre Entschlüsselungsweg
 * (32 Byte, base64 — bewusst NICHT der APP_KEY; erzeugen z. B. mit
 * `php -r "echo base64_encode(random_bytes(32));"`). Offline sichern —
 * Verlust ohne Recovery-Key macht alle Backups wertlos. Der optionale
 * BACKUP_RECOVERY_PUBLIC_KEY (crypto_box-Public-Key, base64) erlaubt eine
 * Zweitentschlüsselung des Datenschlüssels; fehlt er, warnt die UI dauerhaft.
 * Beide Schlüssel gehören NIE ins Backup selbst, nie ins Cloudziel und nie
 * in Logs/Supportexporte.
 */

return [
    // Installations-Backup-Schlüssel (base64, 32 Byte) — nicht APP_KEY.
    'master_key' => env('BACKUP_MASTER_KEY'),

    // Optionaler Recovery-Public-Key (base64, crypto_box keypair public).
    'recovery_public_key' => env('BACKUP_RECOVERY_PUBLIC_KEY'),

    // Teil-Größe des Snapshot-Splits (Bytes); Default 128 MiB.
    'part_size' => (int) env('BACKUP_PART_SIZE', 134_217_728),

    // Dump-Connection (leer = database.default) — z. B. für Read-Replikate.
    'db_connection' => env('BACKUP_DB_CONNECTION', ''),

    // Binary-Pfade (On-Premise-Voraussetzung; Preflight prüft Verfügbarkeit).
    'binaries' => [
        'tar' => env('BACKUP_TAR_BINARY', 'tar'),
        'mysqldump' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
        'pg_dump' => env('BACKUP_PG_DUMP_BINARY', 'pg_dump'),
    ],

    // Retention: Anzahl zu behaltender Generationen je Zeitklasse.
    'retention' => [
        'daily' => (int) env('BACKUP_RETENTION_DAILY', 7),
        'weekly' => (int) env('BACKUP_RETENTION_WEEKLY', 4),
        'monthly' => (int) env('BACKUP_RETENTION_MONTHLY', 12),
    ],

    // Datei-Quellen: Wurzel + relative Pfade, die ins Archiv wandern.
    // Default: storage/app der Installation (Belege, Dokumente, Uploads).
    'files_root' => env('BACKUP_FILES_ROOT', base_path()),
    'files_paths' => ['storage/app'],

    // Quellen-Excludes relativ zum Projektwurzelverzeichnis: Caches,
    // Vendor (reproduzierbar via composer) und kurzlebige Artefakte
    // gehören nicht ins Offsite-Backup.
    'excludes' => [
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/framework/testing',
        'storage/logs',
        'storage/app/backup-work',
    ],

    // Lokales Arbeitsverzeichnis für Snapshot/Verschlüsselung (wird je
    // Lauf geleert; liegt bewusst in den Excludes).
    'work_dir' => env('BACKUP_WORK_DIR', storage_path('app/backup-work')),

    // Anzahl Stichproben-Teile je wöchentlichem Verify-Lauf.
    'verify_sample_parts' => (int) env('BACKUP_VERIFY_SAMPLE_PARTS', 2),
];
