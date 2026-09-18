<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupSnapshotBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup;

use App\Services\Backup\Exceptions\BackupPreflightException;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use CommonToolkit\Helper\Shell;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Snapshot-Erstellung der Cloud-Backups (Feature 017 Phase 32, MVP-362).
 *
 * Quellen: DB-Dump über die DB-Binaries (Muster `scripts/backup.sh`:
 * `mysqldump --single-transaction --quick` / `pg_dump` / SQLite-Kopie,
 * Binary-Pfade in `config/backup_targets.php`) + `storage/app`
 * (abzüglich Excludes) + Inventar-/Versionsinfo → EIN `tar`-Archiv im
 * Arbeitsverzeichnis, anschließend Teil-Split (Default 128 MiB).
 * Preflight prüft Binaries + Arbeitsverzeichnis VOR dem Lauf.
 */
class BackupSnapshotBuilder {
    private const PROCESS_TIMEOUT = 3600.0;

    /**
     * Prüft alle On-Premise-Voraussetzungen; wirft mit klarer Meldung.
     */
    public function preflight(): void {
        $workDir = $this->workRoot();
        try {
            Folder::create($workDir, 0770, true);
        } catch (Throwable) {
            throw new BackupPreflightException("Backup-Arbeitsverzeichnis nicht anlegbar: {$workDir}");
        }
        if (!Folder::isWritable($workDir)) {
            throw new BackupPreflightException("Backup-Arbeitsverzeichnis nicht beschreibbar: {$workDir}");
        }

        $this->resolveBinary('tar');

        $driver = $this->driver();
        match ($driver) {
            'mysql', 'mariadb' => $this->resolveBinary('mysqldump'),
            'pgsql' => $this->resolveBinary('pg_dump'),
            'sqlite' => $this->assertSqliteFile(),
            default => throw new BackupPreflightException("Backup unterstützt den DB-Treiber '{$driver}' nicht."),
        };
    }

    /**
     * Baut den vollständigen Klartext-Snapshot und liefert Pfad + Größe.
     *
     * @return array{tar_path: string, plain_size: int, sources: array<string, string>}
     */
    public function build(string $snapshotUuid): array {
        $this->preflight();

        $dir = $this->workRoot() . '/' . $snapshotUuid;
        $metaDir = $dir . '/meta';
        try {
            Folder::create($metaDir, 0770, true);
        } catch (Throwable) {
            throw new BackupPreflightException("Snapshot-Verzeichnis nicht anlegbar: {$metaDir}");
        }

        $dumpPath = $this->dumpDatabase($metaDir);
        File::write($metaDir . '/inventory.json', JsonHelper::encode($this->inventory($snapshotUuid), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $tarPath = $dir . '/snapshot.tar';
        $command = [$this->resolveBinary('tar'), '-cf', $tarPath];
        foreach ((array) config('backup_targets.excludes', []) as $exclude) {
            $command[] = '--exclude=' . $exclude;
        }
        // Reihenfolge: erst die Datei-Quellen relativ zur Installation, dann
        // die Meta-Dateien (Dump + Inventar) relativ zum Snapshot-Verzeichnis.
        $filesRoot = (string) config('backup_targets.files_root', base_path());
        /** @var list<string> $filesPaths */
        $filesPaths = (array) config('backup_targets.files_paths', ['storage/app']);
        array_push($command, '-C', $filesRoot, ...$filesPaths);
        array_push($command, '-C', $dir, 'meta');

        $result = Shell::run($command, self::PROCESS_TIMEOUT);
        if (!$result->isSuccessful()) {
            throw new BackupPreflightException('tar-Aufruf fehlgeschlagen: ' . ($result->timedOut ? 'Timeout' : mb_substr(trim($result->errorOutput), 0, 300)));
        }

        return [
            'tar_path' => $tarPath,
            'plain_size' => File::size($tarPath),
            'sources' => ['database' => basename($dumpPath), 'files' => 'storage/app', 'inventory' => 'meta/inventory.json'],
        ];
    }

    /**
     * Zerlegt das Archiv in Klartext-Teile fester Größe (letzter Teil kürzer).
     *
     * @return list<string> Pfade der Teil-Dateien in Reihenfolge
     */
    public function splitParts(string $tarPath, ?int $partSize = null): array {
        $partSize ??= (int) config('backup_targets.part_size', 134_217_728);
        if ($partSize < 1_048_576) {
            $partSize = 1_048_576; // Untergrenze 1 MiB — Schutz vor Fehlkonfiguration
        }

        $in = File::openStream($tarPath, 'rb');
        if ($in === false) {
            throw new BackupPreflightException("Snapshot-Archiv nicht lesbar: {$tarPath}");
        }

        $paths = [];
        $partNo = 0;
        try {
            do {
                $partNo++;
                $partPath = $tarPath . '.part-' . $partNo;
                $out = File::openStream($partPath, 'wb');
                if ($out === false) {
                    throw new BackupPreflightException("Teil-Datei nicht schreibbar: {$partPath}");
                }
                $written = 0;
                while ($written < $partSize && !feof($in)) {
                    $chunk = fread($in, max(1, min(1_048_576, $partSize - $written)));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    fwrite($out, $chunk);
                    $written += strlen($chunk);
                }
                fclose($out);
                if ($written === 0 && $partNo > 1) {
                    File::delete($partPath); // leerer Überhang nach exakt aufgehender Größe
                    $partNo--;
                    break;
                }
                $paths[] = $partPath;
            } while (!feof($in));
        } finally {
            fclose($in);
        }

        return $paths;
    }

    /** Räumt das Arbeitsverzeichnis eines Snapshots vollständig ab. */
    public function cleanup(string $snapshotUuid): void {
        $dir = $this->workRoot() . '/' . $snapshotUuid;
        if (!Folder::exists($dir)) {
            return;
        }

        try {
            Folder::delete($dir, true);
        } catch (Throwable) {
            // Best effort: Reste räumt der nächste Lauf bzw. die Retention ab.
        }
    }

    private function dumpDatabase(string $targetDir): string {
        $connection = $this->dumpConnection();
        $cfg = (array) config("database.connections.{$connection}", []);
        $driver = $this->driver();

        if ($driver === 'sqlite') {
            $source = (string) ($cfg['database'] ?? '');
            $target = $targetDir . '/db.sqlite';
            try {
                File::copy($source, $target);
            } catch (Throwable) {
                throw new BackupPreflightException("SQLite-Datenbank nicht kopierbar: {$source}");
            }

            return $target;
        }

        // Das Dump-Binary schreibt die Datei selbst (--file/--result-file):
        // Schreibfehler (Platte voll) enden so im Exit-Code statt still.
        $target = $targetDir . '/db.sql';
        if ($driver === 'pgsql') {
            $command = [
                $this->resolveBinary('pg_dump'),
                '--format=plain', '--no-owner', '--file=' . $target,
                '-h', (string) ($cfg['host'] ?? '127.0.0.1'),
                '-p', (string) ($cfg['port'] ?? '5432'),
                '-U', (string) ($cfg['username'] ?? ''),
                '-d', (string) ($cfg['database'] ?? ''),
            ];
            $env = ['PGPASSWORD' => (string) ($cfg['password'] ?? '')];
        } else {
            $command = [
                $this->resolveBinary('mysqldump'),
                '--single-transaction', '--quick', '--no-tablespaces', '--result-file=' . $target,
                '-h', (string) ($cfg['host'] ?? '127.0.0.1'),
                '-P', (string) ($cfg['port'] ?? '3306'),
                '-u', (string) ($cfg['username'] ?? ''),
                (string) ($cfg['database'] ?? ''),
            ];
            // Passwort über ENV statt Argument — nie in der Prozessliste sichtbar.
            $env = ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')];
        }

        $result = Shell::run($command, self::PROCESS_TIMEOUT, $env);
        if (!$result->isSuccessful() || !File::isFile($target)) {
            try {
                File::delete($target);
            } catch (Throwable) {
                // Teil-Dump bleibt im Snapshot-Verzeichnis; cleanup() räumt es ab.
            }

            throw new BackupPreflightException('DB-Dump fehlgeschlagen: ' . ($result->timedOut ? 'Timeout' : mb_substr(trim($result->errorOutput), 0, 300)));
        }

        return $target;
    }

    /** @return array<string, mixed> */
    private function inventory(string $snapshotUuid): array {
        $migration = null;
        try {
            $migration = DB::table('migrations')->orderByDesc('id')->value('migration');
        } catch (\Throwable) {
            // Inventar bleibt ohne Migrationsstand nutzbar.
        }

        return [
            'snapshot_uuid' => $snapshotUuid,
            'generated_at' => now()->toIso8601String(),
            'app_version' => (string) config('app.version'),
            'app_url' => (string) config('app.url'),
            'php_version' => PHP_VERSION,
            'db_driver' => $this->driver(),
            'latest_migration' => $migration,
        ];
    }

    /** Dump-Connection: konfigurierbar (Tests/Replikate), Default = App-DB. */
    private function dumpConnection(): string {
        $configured = (string) config('backup_targets.db_connection', '');

        return $configured !== '' ? $configured : (string) config('database.default');
    }

    private function driver(): string {
        return (string) config('database.connections.' . $this->dumpConnection() . '.driver', '');
    }

    private function workRoot(): string {
        return rtrim((string) config('backup_targets.work_dir'), '/');
    }

    private function resolveBinary(string $name): string {
        $configured = (string) config("backup_targets.binaries.{$name}", $name);
        if (str_contains($configured, '/')) {
            if (!File::isFile($configured) || !is_executable($configured)) {
                throw new BackupPreflightException("Backup-Binary nicht ausführbar: {$configured} ({$name})");
            }

            return $configured;
        }

        $resolved = (new ExecutableFinder())->find($configured);
        if ($resolved === null) {
            throw new BackupPreflightException(
                "Backup-Binary '{$configured}' nicht gefunden — Pfad in config/backup_targets.php (BACKUP_" . strtoupper($name) . "_BINARY) setzen.",
            );
        }

        return $resolved;
    }

    private function assertSqliteFile(): void {
        $database = (string) config('database.connections.' . $this->dumpConnection() . '.database', '');
        if ($database === '' || $database === ':memory:' || !File::isFile($database)) {
            throw new BackupPreflightException(
                'SQLite-Backup braucht eine dateibasierte Datenbank (kein :memory:).',
            );
        }
    }
}
