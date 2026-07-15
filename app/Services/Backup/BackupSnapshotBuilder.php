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
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\{ExecutableFinder, Process};

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
        if (!is_dir($workDir) && !@mkdir($workDir, 0770, true)) {
            throw new BackupPreflightException("Backup-Arbeitsverzeichnis nicht anlegbar: {$workDir}");
        }
        if (!is_writable($workDir)) {
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
        if (!@mkdir($metaDir, 0770, true) && !is_dir($metaDir)) {
            throw new BackupPreflightException("Snapshot-Verzeichnis nicht anlegbar: {$metaDir}");
        }

        $dumpPath = $this->dumpDatabase($metaDir);
        file_put_contents($metaDir . '/inventory.json', json_encode($this->inventory($snapshotUuid), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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

        $process = new Process($command, timeout: self::PROCESS_TIMEOUT);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new BackupPreflightException('tar-Aufruf fehlgeschlagen: ' . mb_substr(trim($process->getErrorOutput()), 0, 300));
        }

        return [
            'tar_path' => $tarPath,
            'plain_size' => (int) filesize($tarPath),
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

        $in = @fopen($tarPath, 'rb');
        if ($in === false) {
            throw new BackupPreflightException("Snapshot-Archiv nicht lesbar: {$tarPath}");
        }

        $paths = [];
        $partNo = 0;
        try {
            do {
                $partNo++;
                $partPath = $tarPath . '.part-' . $partNo;
                $out = fopen($partPath, 'wb');
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
                    @unlink($partPath); // leerer Überhang nach exakt aufgehender Größe
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
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    private function dumpDatabase(string $targetDir): string {
        $connection = $this->dumpConnection();
        $cfg = (array) config("database.connections.{$connection}", []);
        $driver = $this->driver();

        if ($driver === 'sqlite') {
            $source = (string) ($cfg['database'] ?? '');
            $target = $targetDir . '/db.sqlite';
            if (!@copy($source, $target)) {
                throw new BackupPreflightException("SQLite-Datenbank nicht kopierbar: {$source}");
            }

            return $target;
        }

        $target = $targetDir . '/db.sql';
        $out = fopen($target, 'wb');
        if ($out === false) {
            throw new BackupPreflightException("Dump-Datei nicht schreibbar: {$target}");
        }

        if ($driver === 'pgsql') {
            $command = [
                $this->resolveBinary('pg_dump'),
                '--format=plain', '--no-owner',
                '-h', (string) ($cfg['host'] ?? '127.0.0.1'),
                '-p', (string) ($cfg['port'] ?? '5432'),
                '-U', (string) ($cfg['username'] ?? ''),
                '-d', (string) ($cfg['database'] ?? ''),
            ];
            $env = ['PGPASSWORD' => (string) ($cfg['password'] ?? '')];
        } else {
            $command = [
                $this->resolveBinary('mysqldump'),
                '--single-transaction', '--quick', '--no-tablespaces',
                '-h', (string) ($cfg['host'] ?? '127.0.0.1'),
                '-P', (string) ($cfg['port'] ?? '3306'),
                '-u', (string) ($cfg['username'] ?? ''),
                (string) ($cfg['database'] ?? ''),
            ];
            // Passwort über ENV statt Argument — nie in der Prozessliste sichtbar.
            $env = ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')];
        }

        $process = new Process($command, env: $env, timeout: self::PROCESS_TIMEOUT);
        $exitCode = $process->run(function (string $type, string $buffer) use ($out): void {
            if ($type === Process::OUT) {
                fwrite($out, $buffer);
            }
        });
        fclose($out);

        if ($exitCode !== 0) {
            @unlink($target);

            throw new BackupPreflightException('DB-Dump fehlgeschlagen: ' . mb_substr(trim($process->getErrorOutput()), 0, 300));
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
            if (!is_file($configured) || !is_executable($configured)) {
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
        if ($database === '' || $database === ':memory:' || !is_file($database)) {
            throw new BackupPreflightException(
                'SQLite-Backup braucht eine dateibasierte Datenbank (kein :memory:).',
            );
        }
    }
}
