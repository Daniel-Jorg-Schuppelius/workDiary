<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeBackupTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Contracts\BackupTarget;
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * In-Memory-Backupziel für die Orchestrierungs-/Verify-/Restore-Tests
 * (Feature 017 Phase 32): speichert hochgeladene Objekte pfadbasiert,
 * unterstützt Fehlinjektion (Upload-Ausfall, Byte-Manipulation) und
 * rekursive Ordner-Löschung wie die echten Provider.
 */
class FakeBackupTarget implements BackupTarget {
    /** @var array<string, string> Pfad → Inhalt */
    public array $files = [];

    /** @var list<string> */
    public array $folders = [];

    public ?int $quotaTotal = null;

    public ?int $quotaUsed = null;

    /** Anzahl Uploads, die (noch) fehlschlagen sollen. */
    public int $failUploads = 0;

    public function backupAccount(BackupTargetConnection $connection): BackupAccount {
        return new BackupAccount('fake-account', 'Fake Backup <fake@example.org>');
    }

    public function backupQuota(BackupTargetConnection $connection): array {
        return ['total' => $this->quotaTotal, 'used' => $this->quotaUsed];
    }

    public function backupEnsureFolder(BackupTargetConnection $connection, string $path): string {
        $path = trim($path, '/');
        if (!in_array($path, $this->folders, true)) {
            $this->folders[] = $path;
        }

        return 'dir:' . $path;
    }

    public function backupList(BackupTargetConnection $connection, string $prefix): array {
        $prefix = trim($prefix, '/');
        $children = [];

        foreach (array_keys($this->files) as $path) {
            if (!str_starts_with($path, $prefix . '/')) {
                continue;
            }
            $rest = substr($path, strlen($prefix) + 1);
            $segment = explode('/', $rest)[0];
            if (str_contains($rest, '/')) {
                $children['dir:' . $prefix . '/' . $segment] = new BackupRemoteObject('dir:' . $prefix . '/' . $segment, $segment, 0);
            } else {
                $children['file:' . $path] = new BackupRemoteObject('file:' . $path, $segment, strlen($this->files[$path]));
            }
        }

        return array_values($children);
    }

    public function backupUploadPart(BackupTargetConnection $connection, string $localPath, string $remoteName): string {
        if ($this->failUploads > 0) {
            $this->failUploads--;

            throw new RuntimeException('Fake-Upload-Ausfall (injiziert).');
        }

        $this->files[trim($remoteName, '/')] = (string) file_get_contents($localPath);

        return 'file:' . trim($remoteName, '/');
    }

    public function backupDownload(BackupTargetConnection $connection, string $remoteRef): StreamInterface {
        $path = str_starts_with($remoteRef, 'file:') ? substr($remoteRef, 5) : $remoteRef;
        if (!array_key_exists($path, $this->files)) {
            throw new RuntimeException("Fake-Objekt fehlt: {$path}");
        }

        return Utils::streamFor($this->files[$path]);
    }

    public function backupDelete(BackupTargetConnection $connection, string $remoteRef): bool {
        if (str_starts_with($remoteRef, 'dir:')) {
            $prefix = substr($remoteRef, 4);
            foreach (array_keys($this->files) as $path) {
                if (str_starts_with($path, $prefix . '/')) {
                    unset($this->files[$path]);
                }
            }
            $this->folders = array_values(array_filter($this->folders, static fn (string $f): bool => $f !== $prefix));

            return true;
        }

        unset($this->files[str_starts_with($remoteRef, 'file:') ? substr($remoteRef, 5) : $remoteRef]);

        return true;
    }

    /** Manipuliert ein gespeichertes Objekt (Bitflip) — für Verify-Tests. */
    public function tamper(string $path): void {
        $path = trim($path, '/');
        $content = $this->files[$path] ?? throw new RuntimeException("Fake-Objekt fehlt: {$path}");
        $offset = intdiv(strlen($content), 2);
        $content[$offset] = chr(ord($content[$offset]) ^ 0x01);
        $this->files[$path] = $content;
    }
}
