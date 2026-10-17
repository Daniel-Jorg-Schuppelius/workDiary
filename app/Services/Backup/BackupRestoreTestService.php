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

use App\Enums\Backup\{BackupGenerationStatus, RestoreTestResult};
use App\Models\Backup\BackupGeneration;
use App\Models\Platform\RestoreTest;
use App\Plugins\Contracts\BackupTarget;
use App\Services\Backup\Exceptions\BackupPreflightException;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use CommonToolkit\Helper\Shell;
use CommonToolkit\ValueObjects\ByteSize;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Restore-Test der Cloud-Backups (Feature 017 Phase 32, MVP-365): voller
 * Download in ein ISOLIERTES Zielverzeichnis, Entschlüsselung, tar-Entpacken
 * und DB-Dump-Integritätscheck — die laufende Produktion wird NIE
 * überschrieben. Protokolliert RPO/RTO an der Generation und trägt den Test
 * ins plattformweite Restore-Test-Register (Feature 017) ein.
 *
 * Geplanter Lauf (MVP-1204): {@see runForLatestGenerations()} testet die
 * jüngste Generation jeder Verbindung, räumt das Zielverzeichnis wieder weg
 * und trägt auch Fehlschläge ins Register ein.
 */
class BackupRestoreTestService {
    // Gemeinsame Adapter-Auflösung (Vollaudit 2026-07, N34).
    use \App\Services\Backup\Concerns\ResolvesBackupTarget;

    public function __construct(
        private readonly BackupDecrypter $decrypter,
    ) {}

    /**
     * Jüngste committete oder verifizierte Generation jeder Verbindung — wie
     * der Verify-Lauf. Ohne Generationen gibt es nichts zu testen.
     *
     * @return array{passed: list<string>, failed: array<string, string>, target_dirs: array<string, string>}
     */
    public function runForLatestGenerations(?BackupTarget $adapter = null, bool $keep = false): array {
        $result = ['passed' => [], 'failed' => [], 'target_dirs' => []];

        $generations = BackupGeneration::query()
            ->whereNotNull('connection_id')
            ->whereIn('status', [BackupGenerationStatus::Committed->value, BackupGenerationStatus::Verified->value])
            ->orderByDesc('committed_at')
            ->get()
            ->unique('connection_id');

        foreach ($generations as $generation) {
            $this->attempt($generation, null, $keep, $adapter, $result);
        }

        return $result;
    }

    /**
     * Eine Generation, protokolliert wie der geplante Lauf (Fehlschlag landet
     * im Register, Zielverzeichnis wird ohne $keep wieder entfernt).
     *
     * @return array{passed: list<string>, failed: array<string, string>, target_dirs: array<string, string>}
     */
    public function runRecorded(BackupGeneration $generation, ?string $targetDir = null, bool $keep = false, ?BackupTarget $adapter = null): array {
        $result = ['passed' => [], 'failed' => [], 'target_dirs' => []];
        $this->attempt($generation, $targetDir, $keep, $adapter, $result);

        return $result;
    }

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
        if (Folder::exists($targetDir) && !Folder::isEmpty($targetDir)) {
            throw new BackupPreflightException("Zielverzeichnis ist nicht leer: {$targetDir}");
        }
        try {
            Folder::create($targetDir, 0770, true);
        } catch (Throwable) {
            throw new BackupPreflightException("Zielverzeichnis nicht anlegbar: {$targetDir}");
        }

        $parts = $generation->parts()->orderBy('part_no')->get();
        $this->assertFreeSpace($targetDir, (int) $parts->sum('plain_size'), (int) $parts->max('cipher_size'));

        $startedAt = microtime(true);

        // 1) Commit laden + öffnen (Signatur zuerst).
        $document = (string) $adapter->backupDownload($connection, (string) $generation->commit_remote_ref);
        $opened = $this->decrypter->openCommitDocument($document);
        $dataKey = $opened['data_key'];

        // 2) Alle Teile herunterladen, Hashes prüfen, entschlüsseln, zusammensetzen.
        // Der Generator liefert die geprüften Klartext-Teile blockweise ins Archiv.
        $tarPath = $targetDir . '/snapshot.tar';
        $plainChunks = (function () use ($parts, $adapter, $connection, $targetDir, $dataKey, $generation): \Generator {
            foreach ($parts as $part) {
                $cipherPath = $targetDir . '/part-' . $part->part_no . '.enc';
                $plainPath = $targetDir . '/part-' . $part->part_no . '.plain';

                $stream = $adapter->backupDownload($connection, (string) $part->remote_ref);
                $cipherChunks = (static function () use ($stream): \Generator {
                    while (!$stream->eof()) {
                        yield $stream->read(1_048_576);
                    }
                })();
                if (File::writeStream($cipherPath, $cipherChunks) === false) {
                    throw new BackupPreflightException("Teil {$part->part_no} nicht schreibbar.");
                }

                if ($this->sha256($cipherPath) !== $part->cipher_sha256) {
                    throw new BackupPreflightException("Teil {$part->part_no}: Ciphertext-Hash weicht ab.");
                }
                $this->decrypter->decryptPart($cipherPath, $plainPath, $dataKey, $generation->snapshot_uuid, $part->part_no);
                if ($this->sha256($plainPath) !== $part->plain_sha256) {
                    throw new BackupPreflightException("Teil {$part->part_no}: Klartext-Hash weicht ab.");
                }

                yield from File::readChunks($plainPath, 1_048_576, strict: true);
                foreach ([$cipherPath, $plainPath] as $leftover) {
                    try {
                        File::delete($leftover);
                    } catch (Throwable) {
                        // Best effort: das Zielverzeichnis ist ohnehin isoliert.
                    }
                }
            }
        })();
        if (File::writeStream($tarPath, $plainChunks) === false) {
            throw new BackupPreflightException('Archivdatei nicht schreibbar.');
        }

        // 3) tar entpacken + Kernartefakte prüfen (Dump + Inventar).
        $extractDir = $targetDir . '/extracted';
        try {
            Folder::create($extractDir, 0770, true);
        } catch (Throwable) {
            throw new BackupPreflightException('Entpack-Verzeichnis nicht anlegbar.');
        }
        $tarBinary = (new ExecutableFinder())->find((string) config('backup_targets.binaries.tar', 'tar'))
            ?? throw new BackupPreflightException('tar-Binary nicht gefunden.');
        $result = Shell::run([$tarBinary, '-xf', $tarPath, '-C', $extractDir], 3600.0);
        if (!$result->isSuccessful()) {
            throw new BackupPreflightException('tar-Entpacken fehlgeschlagen: ' . ($result->timedOut ? 'Timeout' : mb_substr(trim($result->errorOutput), 0, 300)));
        }

        $dumpSql = $extractDir . '/meta/db.sql';
        $dumpSqlite = $extractDir . '/meta/db.sqlite';
        if (!File::isFile($dumpSql) && !File::isFile($dumpSqlite)) {
            throw new BackupPreflightException('DB-Dump fehlt im wiederhergestellten Archiv.');
        }
        if (File::isFile($dumpSqlite)) {
            // SQLite-Integritätscheck direkt auf der Kopie.
            $pdo = new \PDO('sqlite:' . $dumpSqlite);
            $statement = $pdo->query('PRAGMA integrity_check');
            $check = $statement === false ? '' : (string) $statement->fetchColumn();
            unset($statement, $pdo);
            if ($check !== 'ok') {
                throw new BackupPreflightException('SQLite-Integritätscheck fehlgeschlagen: ' . mb_substr($check, 0, 100));
            }
        }
        if (!File::isFile($extractDir . '/meta/inventory.json')) {
            throw new BackupPreflightException('Inventar fehlt im wiederhergestellten Archiv.');
        }

        // 4) Protokoll: RPO = Alter des Commits, RTO = Dauer dieses Tests.
        $rto = (int) ceil(microtime(true) - $startedAt);
        $rpo = (int) max(0, now()->getTimestamp() - ($generation->committed_at?->getTimestamp() ?? now()->getTimestamp()));
        $restoredSize = File::size($tarPath);

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
        $testedOn = CarbonImmutable::today();
        RestoreTest::query()->create([
            'source' => 'cloud-backup:' . $connection->provider->value,
            'tested_on' => $testedOn,
            'result' => RestoreTestResult::Passed,
            'scope' => 'Generation ' . $generation->snapshot_uuid,
            'restored_size_bytes' => $restoredSize,
            'duration_minutes' => max(1, (int) ceil($rto / 60)),
            'next_due_on' => RestoreTest::defaultNextDueOn($testedOn, RestoreTestResult::Passed),
        ]);

        return ['rpo_seconds' => $rpo, 'rto_seconds' => $rto, 'restored_size' => $restoredSize, 'target_dir' => $targetDir];
    }

    /**
     * Fehlschlag ins Register: ein gescheiterter Test ist ein Test. Der Grund
     * steht an der Generation; die Verbindung bleibt unberührt, weil die
     * Ursache ebenso lokal (Platz, tar) wie entfernt liegen kann.
     */
    public function recordFailure(BackupGeneration $generation, Throwable $e): RestoreTest {
        $reason = class_basename($e) . ': ' . $e->getMessage();

        $generation->forceFill(['last_error' => mb_substr('Restore-Test: ' . $reason, 0, 300)])->save();

        return RestoreTest::query()->create([
            'source' => 'cloud-backup:' . ($generation->connection?->provider->value ?? 'unbekannt'),
            'tested_on' => CarbonImmutable::today(),
            'result' => RestoreTestResult::Failed,
            'scope' => 'Generation ' . $generation->snapshot_uuid,
            'notes' => mb_substr('Automatischer Restore-Test fehlgeschlagen — ' . $reason, 0, 5000),
        ]);
    }

    /** Isoliert unterhalb des Backup-Arbeitsverzeichnisses (liegt in den Excludes); Zeitstempel verhindert Kollisionen mit stehengelassenen Läufen. */
    public function defaultTargetDir(BackupGeneration $generation): string {
        return rtrim((string) config('backup_targets.work_dir'), '/')
            . '/restore-test/' . $generation->snapshot_uuid . '-' . now()->format('YmdHis');
    }

    /** @param array{passed: list<string>, failed: array<string, string>, target_dirs: array<string, string>} $result */
    private function attempt(BackupGeneration $generation, ?string $targetDir, bool $keep, ?BackupTarget $adapter, array &$result): void {
        $targetDir ??= $this->defaultTargetDir($generation);
        // Nur selbst angelegte Verzeichnisse werden wieder entfernt — ein vorhandenes gehört jemand anderem.
        $created = !Folder::exists($targetDir);
        $result['target_dirs'][$generation->snapshot_uuid] = $targetDir;

        try {
            $this->run($generation, $targetDir, $adapter);
            $result['passed'][] = $generation->snapshot_uuid;
        } catch (Throwable $e) {
            $this->recordFailure($generation, $e);
            $result['failed'][$generation->snapshot_uuid] = class_basename($e);
        } finally {
            if (!$keep && $created) {
                try {
                    Folder::delete($targetDir, true);
                } catch (Throwable) {
                    // Best effort (auch: gar nicht angelegt) — liegt isoliert im Arbeitsverzeichnis.
                }
            }
        }
    }

    /**
     * Voller Download braucht Archiv + Entpacktes (2× Klartext) plus den
     * größten verschlüsselten Teil; ohne Prüfung füllt ein nächtlicher Lauf
     * die Platte des Produktivsystems.
     */
    private function assertFreeSpace(string $targetDir, int $plainSize, int $largestCipherPart): void {
        $required = 2 * $plainSize + $largestCipherPart;
        if ($required <= 0) {
            return;
        }

        // false (nicht ermittelbar) fällt mit <= 0 heraus: dann lieber testen als raten.
        $free = disk_free_space($targetDir);
        if ($free <= 0) {
            return;
        }
        if ((int) $free < $required) {
            throw new BackupPreflightException(sprintf(
                'Zu wenig freier Platz für den Restore-Test: %s frei, etwa %s nötig (%s).',
                ByteSize::ofBytes((int) $free)->format(),
                ByteSize::ofBytes($required)->format(),
                $targetDir,
            ));
        }
    }

    /**
     * Hash der geschriebenen Datei; eine nicht (mehr) lesbare Datei zählt wie
     * vorher als Abweichung, nicht als Ausnahme (Toolkit File::hash wirft).
     */
    private function sha256(string $path): ?string {
        try {
            return File::hash($path);
        } catch (Throwable) {
            return null;
        }
    }
}
