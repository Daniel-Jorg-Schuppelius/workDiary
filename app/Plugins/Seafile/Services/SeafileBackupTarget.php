<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileBackupTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Seafile\Services;

use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Seafile\Api\{SeafileClient, SeafileClientFactory};
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Seafile als systemweites, verschlüsseltes Backupziel (MVP-1348): eine
 * gewählte Bibliothek, darin nur der WorkDiary-eigene Backupbereich
 * (Pseudonym-Pfad). Hochgeladen wird nur Chiffrat, jede Datei als Ganzes über
 * den Upload-Link, danach gegen die lokale Größe geprüft. Getrennt vom
 * lesenden Dokumenteingang: eigene Verbindung, eigener Token.
 */
class SeafileBackupTarget {
    public function __construct(private readonly SeafileClientFactory $clients) {}

    public function account(BackupTargetConnection $connection): BackupAccount {
        $serverUrl = (string) $connection->server_url;
        $account = $this->clients->forBackup($connection)->account();
        $email = $account['email'] ?? throw new RuntimeException('Seafile nennt kein Konto.');

        // `email` ist seit Seafile 11 oft eine interne Kennung; angezeigt wird die Kontaktadresse.
        return new BackupAccount(
            externalId: SelfHostedInstance::key($serverUrl) . '|' . $email,
            label: ($account['contact'] ?? $email) . ' @ ' . SelfHostedInstance::name($serverUrl),
        );
    }

    /**
     * Speicher des Kontos — gilt nur für eigene Bibliotheken; bei einer
     * geteilten zählt die Quota des Eigentümers, dann bleibt er unbekannt.
     *
     * @return array{total: int|null, used: int|null}
     */
    public function quota(BackupTargetConnection $connection): array {
        if (($connection->options['library_own'] ?? false) !== true) {
            return ['total' => null, 'used' => null];
        }
        $account = $this->clients->forBackup($connection)->account();

        return ['total' => $account['total'], 'used' => $account['usage']];
    }

    /** Legt den Backupbereich samt Elternordnern an; Referenz ist der Pfad. */
    public function ensureFolder(BackupTargetConnection $connection, string $path): string {
        if (trim($path, '/') !== '') {
            $this->clients->forBackup($connection)->makeFolder($this->repoId($connection), $path);
        }

        return trim($path, '/');
    }

    /**
     * Dateien und Ordner direkt unter `$prefix`; ein fehlender Ordner ist leer.
     *
     * @return list<BackupRemoteObject>
     */
    public function listObjects(BackupTargetConnection $connection, string $prefix): array {
        $prefix = trim($prefix, '/');
        $client = $this->clients->forBackup($connection);
        $folder = $client->folder($this->repoId($connection), $prefix === '' ? '/' : $prefix);
        if ($folder === null) {
            if (! $client->libraryExists($this->repoId($connection))) {
                throw new RuntimeException('Seafile-Bibliothek des Backupziels nicht gefunden.');
            }

            return [];
        }
        $objects = [];
        foreach ($folder['entries'] as $entry) {
            $name = is_string($entry['name'] ?? null) ? $entry['name'] : '';
            if ($name === '' || str_contains($name, '/')) {
                continue;
            }
            $mtime = $entry['mtime'] ?? null;
            $objects[] = new BackupRemoteObject(
                ref: $prefix === '' ? $name : $prefix . '/' . $name,
                name: $name,
                size: is_int($entry['size'] ?? null) ? $entry['size'] : 0,
                modifiedAt: is_int($mtime) && $mtime > 0 ? CarbonImmutable::createFromTimestampUTC($mtime)->toIso8601String() : null,
            );
        }

        return $objects;
    }

    /** Lädt die Datei hoch (gleichnamige wird ersetzt) und prüft die Größe am Ziel. */
    public function uploadPart(BackupTargetConnection $connection, string $localPath, string $remoteName): string {
        $remoteName = trim($remoteName, '/');
        $client = $this->clients->forBackup($connection);
        $repoId = $this->repoId($connection);
        $parent = str_contains($remoteName, '/') ? substr($remoteName, 0, (int) strrpos($remoteName, '/')) : '';
        $name = basename($remoteName);

        $uploaded = $client->upload($repoId, $parent === '' ? '/' : $parent, $localPath, $name);
        // Ohne Ersetzungsrecht legt Seafile „Name (1)“ an — dann wäre die Größe am Zielnamen die der alten Datei.
        if ($uploaded['name'] !== $name) {
            throw new RuntimeException('Seafile-Upload unter abweichendem Namen abgelegt.');
        }
        $localSize = File::size($localPath);
        if ($uploaded['size'] !== $localSize) {
            throw new RuntimeException(sprintf('Seafile-Upload unvollständig: am Ziel %s B, lokal %d B.', $uploaded['size'] ?? '–', $localSize));
        }

        return $remoteName;
    }

    public function download(BackupTargetConnection $connection, string $remoteRef): StreamInterface {
        return $this->clients->forBackup($connection)->download($this->repoId($connection), $remoteRef);
    }

    /** Löscht ein eigenes Objekt (Datei oder Ordner samt Inhalt); idempotent. */
    public function delete(BackupTargetConnection $connection, string $remoteRef): bool {
        return $this->clients->forBackup($connection)->delete($this->repoId($connection), $remoteRef);
    }

    private function repoId(BackupTargetConnection $connection): string {
        $repoId = (string) ($connection->options['library_id'] ?? '');
        if (! SeafileClient::isRepoId($repoId)) {
            throw new RuntimeException('Seafile-Backupziel ohne gültige Bibliothek.');
        }

        return $repoId;
    }
}
