<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRestoreTestService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup;

use App\Enums\Backup\RestoreTestResult;
use App\Models\Backup\BackupGeneration;
use App\Models\RestoreTest;
use App\Plugins\Contracts\BackupTarget;
use App\Services\Backup\Exceptions\BackupPreflightException;
use Symfony\Component\Process\{ExecutableFinder, Process};

/**
 * Restore-Test der Cloud-Backups (Feature 017 Phase 32, MVP-365): voller
 * Download in ein ISOLIERTES Zielverzeichnis, Entschlüsselung, tar-Entpacken
 * und DB-Dump-Integritätscheck — die laufende Produktion wird NIE
 * überschrieben. Protokolliert RPO/RTO an der Generation und trägt den Test
 * ins plattformweite Restore-Test-Register (Feature 017) ein.
 */
class BackupRestoreTestService {
    // Gemeinsame Adapter-Auflösung (Vollaudit 2026-07, N34).
    use \App\Services\Backup\Concerns\ResolvesBackupTarget;

    public function __construct(
        private readonly BackupDecrypter $decrypter,
    ) {}

    /**
     * @return array{rpo_seconds: int, rto_seconds: int, restored_size: int, target_dir: string}
     */
    public function run(BackupGeneration $generation, string $targetDir, ?BackupTarget $adapter = null): array {
        $connection = $generation->connection;
        if ($connection === null) {
            throw new BackupPreflightException('Generation hat keine Verbindung mehr — Restore-Test unmöglich.');
        }
        $adapter ??= $this->adapter($connection);

        // Isolation: Zielverzeichnis muss neu oder leer sein — nie Produktion.
        $targetDir = rtrim($targetDir, '/');
        if ($targetDir === '' || $targetDir === base_path() || str_starts_with(base_path(), $targetDir)) {
            throw new BackupPreflightException('Restore-Test verlangt ein isoliertes Zielverzeichnis.');
        }
        if (is_dir($targetDir) && count((array) scandir($targetDir)) > 2) {
            throw new BackupPreflightException("Zielverzeichnis ist nicht leer: {$targetDir}");
        }
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0770, true)) {
            throw new BackupPreflightException("Zielverzeichnis nicht anlegbar: {$targetDir}");
        }

        $startedAt = microtime(true);

        // 1) Commit laden + öffnen (Signatur zuerst).
        $document = (string) $adapter->backupDownload($connection, (string) $generation->commit_remote_ref);
        $opened = $this->decrypter->openCommitDocument($document);
        $dataKey = $opened['data_key'];

        // 2) Alle Teile herunterladen, Hashes prüfen, entschlüsseln, zusammensetzen.
        $tarPath = $targetDir . '/snapshot.tar';
        $tar = fopen($tarPath, 'wb');
        if ($tar === false) {
            throw new BackupPreflightException('Archivdatei nicht schreibbar.');
        }

        try {
            $parts = $generation->parts()->orderBy('part_no')->get();
            foreach ($parts as $part) {
                $cipherPath = $targetDir . '/part-' . $part->part_no . '.enc';
                $plainPath = $targetDir . '/part-' . $part->part_no . '.plain';

                $stream = $adapter->backupDownload($connection, (string) $part->remote_ref);
                $out = fopen($cipherPath, 'wb');
                if ($out === false) {
                    throw new BackupPreflightException("Teil {$part->part_no} nicht schreibbar.");
                }
                while (!$stream->eof()) {
                    fwrite($out, $stream->read(1_048_576));
                }
                fclose($out);

                if (hash_file('sha256', $cipherPath) !== $part->cipher_sha256) {
                    throw new BackupPreflightException("Teil {$part->part_no}: Ciphertext-Hash weicht ab.");
                }
                $this->decrypter->decryptPart($cipherPath, $plainPath, $dataKey, $generation->snapshot_uuid, $part->part_no);
                if (hash_file('sha256', $plainPath) !== $part->plain_sha256) {
                    throw new BackupPreflightException("Teil {$part->part_no}: Klartext-Hash weicht ab.");
                }

                $in = fopen($plainPath, 'rb');
                if ($in === false) {
                    throw new BackupPreflightException("Teil {$part->part_no} nicht lesbar.");
                }
                stream_copy_to_stream($in, $tar);
                fclose($in);
                @unlink($cipherPath);
                @unlink($plainPath);
            }
        } finally {
            fclose($tar);
        }

        // 3) tar entpacken + Kernartefakte prüfen (Dump + Inventar).
        $extractDir = $targetDir . '/extracted';
        if (!@mkdir($extractDir, 0770, true) && !is_dir($extractDir)) {
            throw new BackupPreflightException('Entpack-Verzeichnis nicht anlegbar.');
        }
        $tarBinary = (new ExecutableFinder())->find((string) config('backup_targets.binaries.tar', 'tar'))
            ?? throw new BackupPreflightException('tar-Binary nicht gefunden.');
        $process = new Process([$tarBinary, '-xf', $tarPath, '-C', $extractDir], timeout: 3600.0);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new BackupPreflightException('tar-Entpacken fehlgeschlagen: ' . mb_substr(trim($process->getErrorOutput()), 0, 300));
        }

        $dumpSql = $extractDir . '/meta/db.sql';
        $dumpSqlite = $extractDir . '/meta/db.sqlite';
        if (!is_file($dumpSql) && !is_file($dumpSqlite)) {
            throw new BackupPreflightException('DB-Dump fehlt im wiederhergestellten Archiv.');
        }
        if (is_file($dumpSqlite)) {
            // SQLite-Integritätscheck direkt auf der Kopie.
            $pdo = new \PDO('sqlite:' . $dumpSqlite);
            $statement = $pdo->query('PRAGMA integrity_check');
            $check = $statement === false ? '' : (string) $statement->fetchColumn();
            unset($statement, $pdo);
            if ($check !== 'ok') {
                throw new BackupPreflightException('SQLite-Integritätscheck fehlgeschlagen: ' . mb_substr($check, 0, 100));
            }
        }
        if (!is_file($extractDir . '/meta/inventory.json')) {
            throw new BackupPreflightException('Inventar fehlt im wiederhergestellten Archiv.');
        }

        // 4) Protokoll: RPO = Alter des Commits, RTO = Dauer dieses Tests.
        $rto = (int) ceil(microtime(true) - $startedAt);
        $rpo = (int) max(0, now()->getTimestamp() - ($generation->committed_at?->getTimestamp() ?? now()->getTimestamp()));
        $restoredSize = (int) filesize($tarPath);

        $generation->forceFill([
            'restore_tested_at' => now(),
            'restore_rpo_seconds' => $rpo,
            'restore_rto_seconds' => $rto,
        ])->save();
        $generation->audit('backup.restoreTested', [
            'snapshot_uuid' => $generation->snapshot_uuid,
            'rpo_seconds' => $rpo,
            'rto_seconds' => $rto,
        ]);

        // Plattformweites Restore-Test-Register (Feature 017).
        RestoreTest::query()->create([
            'source' => 'cloud-backup:' . $connection->provider->value,
            'tested_on' => now()->toDateString(),
            'result' => RestoreTestResult::Passed,
            'scope' => 'Generation ' . $generation->snapshot_uuid,
            'restored_size_bytes' => $restoredSize,
            'duration_minutes' => max(1, (int) ceil($rto / 60)),
        ]);

        return ['rpo_seconds' => $rpo, 'rto_seconds' => $rto, 'restored_size' => $restoredSize, 'target_dir' => $targetDir];
    }

}
