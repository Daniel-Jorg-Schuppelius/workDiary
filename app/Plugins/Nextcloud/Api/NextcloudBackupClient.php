<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudBackupClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud\Api;

use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Nextcloud\Contracts\NextcloudTransportFactory;
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Nextcloud als systemweites, verschlüsseltes Cloud-BACKUPZIEL (Feature 017
 * Phase 32, MVP-383). Getrennt vom Dokumenteingang ({@see NextcloudIntakeClient}):
 * eigene Verbindung, eigene Zugangsdaten. Arbeitet ausschließlich im
 * WorkDiary-eigenen Backupbereich (Pseudonym-Pfad) und lädt nur CIPHERTEXT —
 * die serverseitige Nextcloud-Verschlüsselung ersetzt niemals die
 * clientseitige WorkDiary-Verschlüsselung. Resumable Uploads laufen über
 * Chunked Upload v2 und werden remote über die Größe verifiziert.
 */
class NextcloudBackupClient {
    private readonly NextcloudWebdavClient $transport;

    public function __construct(private readonly BackupTargetConnection $connection) {
        $this->transport = app(NextcloudTransportFactory::class)->forCredentials(
            (string) $connection->server_url,
            (string) $connection->username,
            (string) $connection->access_token,
        );
    }

    public function account(): BackupAccount {
        if (! $this->transport->ping()) {
            throw new RuntimeException('Nextcloud-Anmeldung fehlgeschlagen (PROPFIND ohne 207).');
        }

        $host = $this->transport->serverHost();

        return new BackupAccount(
            externalId: $host . '|' . (string) $this->connection->username,
            label: (string) $this->connection->username . ' @ ' . $host,
        );
    }

    /** @return array{total: int|null, used: int|null} */
    public function quota(): array {
        return $this->transport->quota();
    }

    /** Stellt den Backupbereich (Pseudonym-Pfad) sicher; Referenz = Pfad. */
    public function ensureFolder(string $path): string {
        $path = trim($path, '/');
        $this->transport->ensureCollection($path);

        return $path;
    }

    /**
     * @return list<BackupRemoteObject>
     */
    public function listObjects(string $prefix): array {
        try {
            $children = $this->transport->listChildren(trim($prefix, '/'));
        } catch (NextcloudNotFoundException) {
            return []; // Prefix existiert (noch) nicht.
        }

        return array_map(
            static fn (array $child): BackupRemoteObject => new BackupRemoteObject(
                ref: $child['path'],
                name: self::baseName($child['path']),
                size: $child['size'],
                modifiedAt: $child['modified'],
            ),
            $children,
        );
    }

    /** Resumable Upload; verifiziert die Remote-Größe. Referenz = Zielpfad. */
    public function uploadPart(string $localPath, string $remoteName): string {
        $remoteName = trim($remoteName, '/');
        $this->transport->uploadResumable($localPath, $remoteName);

        return $remoteName;
    }

    public function download(string $remoteRef): StreamInterface {
        return $this->transport->getStream($remoteRef);
    }

    /** Löscht ein EIGENES Objekt (Datei oder Ordner rekursiv); idempotent. */
    public function delete(string $remoteRef): bool {
        return $this->transport->deletePath($remoteRef);
    }

    private static function baseName(string $path): string {
        $path = rtrim($path, '/');
        $pos = strrpos($path, '/');

        return $pos === false ? $path : substr($path, $pos + 1);
    }
}
