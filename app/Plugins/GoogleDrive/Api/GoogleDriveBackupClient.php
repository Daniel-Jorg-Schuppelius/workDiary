<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveBackupClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleDrive\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\Backup\BackupTargetConnection;
use App\Plugins\GoogleDrive\{GoogleDriveConfig, GoogleDrivePlugin};
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use App\Plugins\Support\{ConnectionTokenStore, PluginHttpFactory};
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Google-Drive-Gateway des Cloud-BACKUPZIELS (Feature 017 Phase 32,
 * MVP-363): resumable Uploads über die Upload-API, Scope `drive.file`
 * (sieht ausschließlich app-erzeugte Dateien — der Backupbereich ist
 * dadurch technisch isoliert). Ordner werden segmentweise per Query
 * aufgelöst/angelegt (ID-Adressierung); die Remote-Größe wird nach dem
 * letzten Chunk verifiziert.
 */
class GoogleDriveBackupClient {
    /** Chunk-Größe: Vielfaches von 256 KiB (Google-Anforderung) — 8 MiB. */
    private const CHUNK_SIZE = 8_388_608;

    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    private \App\Plugins\Support\PluginApiClient $api;

    private string $base;

    private string $uploadBase;

    public function __construct(BackupTargetConnection $connection) {
        $config = GoogleDriveConfig::resolve();
        $factory = app(PluginHttpFactory::class);
        $this->base = $config['api_base'];
        $this->uploadBase = $config['upload_base'];
        $this->api = $factory->client(GoogleDrivePlugin::ID, $this->base);

        $grant = GoogleDriveConfig::isConfigured() ? app(GoogleDriveBackupOAuth::class)->grant() : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($connection, 'granted_scopes', scopeAsArray: true), $grant));
    }

    public function account(): BackupAccount {
        $response = $this->api->getResponse($this->base . '/about', ['fields' => 'user']);
        if (!$response->successful()) {
            throw new RuntimeException('Drive /about fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{user?: array{permissionId?: string, displayName?: string, emailAddress?: string}} $data */
        $data = (array) $response->json();

        return new BackupAccount(
            externalId: (string) ($data['user']['permissionId'] ?? ''),
            label: trim((string) ($data['user']['displayName'] ?? '') . ' <' . (string) ($data['user']['emailAddress'] ?? '') . '>'),
        );
    }

    /** @return array{total: int|null, used: int|null} */
    public function quota(): array {
        $response = $this->api->getResponse($this->base . '/about', ['fields' => 'storageQuota']);
        if (!$response->successful()) {
            throw new RuntimeException('Drive storageQuota fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{storageQuota?: array{limit?: string, usage?: string}} $data */
        $data = (array) $response->json();

        return [
            // limit fehlt bei "unbegrenzt".
            'total' => isset($data['storageQuota']['limit']) ? (int) $data['storageQuota']['limit'] : null,
            'used' => isset($data['storageQuota']['usage']) ? (int) $data['storageQuota']['usage'] : null,
        ];
    }

    /** Legt den Pfad segmentweise an (idempotent); Referenz ist die Datei-ID. */
    public function ensureFolder(string $path): string {
        return $this->resolveFolder($path, create: true) ?? throw new RuntimeException('Drive-Ordneranlage fehlgeschlagen.');
    }

    /**
     * Direkte Einträge unterhalb des Prefix (eigener Backupbereich).
     *
     * @return list<BackupRemoteObject>
     */
    public function listObjects(string $prefix): array {
        $folderId = $this->resolveFolder($prefix, create: false);
        if ($folderId === null) {
            return []; // Prefix existiert (noch) nicht.
        }

        $objects = [];
        $pageToken = null;
        do {
            $query = [
                'q' => sprintf("'%s' in parents and trashed=false", addslashes($folderId)),
                'fields' => 'nextPageToken,files(id,name,size,modifiedTime)',
                'pageSize' => 200,
            ];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }
            $response = $this->api->getResponse($this->base . '/files', $query);
            if (!$response->successful()) {
                throw new RuntimeException('Drive-Listing fehlgeschlagen (HTTP ' . $response->status() . ').');
            }

            /** @var array{files?: list<array<string, mixed>>, nextPageToken?: string} $data */
            $data = (array) $response->json();
            foreach ((array) ($data['files'] ?? []) as $row) {
                $objects[] = new BackupRemoteObject(
                    ref: (string) ($row['id'] ?? ''),
                    name: (string) ($row['name'] ?? ''),
                    size: (int) ($row['size'] ?? 0),
                    modifiedAt: isset($row['modifiedTime']) ? (string) $row['modifiedTime'] : null,
                );
            }
            $pageToken = isset($data['nextPageToken']) && $data['nextPageToken'] !== '' ? (string) $data['nextPageToken'] : null;
        } while ($pageToken !== null);

        return $objects;
    }

    /** Resumable Upload einer lokalen Datei; verifiziert die Remote-Größe. */
    public function uploadPart(string $localPath, string $remoteName): string {
        // Gemeinsame Chunk-Leseschleife (Vollaudit 2026-07, N31); Resumable-
        // Session und 308-Semantik bleiben Drive-spezifisch.
        $size = \App\Plugins\Support\Backup\ChunkedFileReader::size($localPath);

        $folder = str_contains(trim($remoteName, '/'), '/') ? dirname(trim($remoteName, '/')) : '';
        $parentId = $folder === '' ? 'root' : $this->ensureFolder($folder);

        $session = $this->api->postJson(
            $this->uploadBase . '/files?uploadType=resumable&fields=id,size',
            ['name' => basename($remoteName), 'parents' => [$parentId]],
        );
        $uploadUrl = (string) $session->header('Location');
        if (!$session->successful() || $uploadUrl === '') {
            throw new RuntimeException('Drive-Upload-Session fehlgeschlagen (HTTP ' . $session->status() . ').');
        }

        $fileId = '';
        $remoteSize = -1;
        \App\Plugins\Support\Backup\ChunkedFileReader::each($localPath, self::CHUNK_SIZE, function (string $chunk, int $offset) use ($uploadUrl, $size, &$fileId, &$remoteSize): void {
            $last = $offset + strlen($chunk) - 1;
            $response = $this->api->requestResponse('put', $uploadUrl, [
                'headers' => [
                    'Content-Length' => (string) strlen($chunk),
                    'Content-Range' => sprintf('bytes %d-%d/%d', $offset, $last, $size),
                ],
                'body' => $chunk,
            ]);
            // Zwischenchunks: 308 Resume Incomplete; letzter Chunk: 200/201.
            if (!$response->successful() && $response->status() !== 308) {
                throw new RuntimeException('Drive-Chunk-Upload fehlgeschlagen (HTTP ' . $response->status() . ').');
            }
            if ($response->successful()) {
                $fileId = (string) $response->json('id', '');
                $remoteSize = (int) $response->json('size', -1);
            }
        });

        if ($fileId === '' || $remoteSize !== $size) {
            throw new RuntimeException("Drive-Upload unvollständig: remote {$remoteSize} B, lokal {$size} B.");
        }

        return $fileId;
    }

    public function download(string $remoteRef): StreamInterface {
        $response = $this->api->requestResponse('get', $this->base . '/files/' . rawurlencode($remoteRef) . '?alt=media');
        if (!$response->successful()) {
            throw new RuntimeException('Drive-Download fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $response->toPsrResponse()->getBody();
    }

    /** Löscht ein EIGENES Objekt (Datei oder Ordner rekursiv); idempotent. */
    public function delete(string $remoteRef): bool {
        $response = $this->api->deleteResponse($this->base . '/files/' . rawurlencode($remoteRef));
        if ($response->status() === 404) {
            return true;
        }
        if (!$response->successful()) {
            throw new RuntimeException('Drive-Löschung fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return true;
    }

    /** Löst einen Pfad segmentweise zur Ordner-ID auf (optional anlegend). */
    private function resolveFolder(string $path, bool $create): ?string {
        $parentId = 'root';
        foreach (array_values(array_filter(explode('/', trim($path, '/')))) as $segment) {
            $response = $this->api->getResponse($this->base . '/files', [
                'q' => sprintf(
                    "name='%s' and '%s' in parents and mimeType='%s' and trashed=false",
                    addslashes($segment),
                    addslashes($parentId),
                    self::FOLDER_MIME,
                ),
                'fields' => 'files(id)',
                'pageSize' => 1,
            ]);
            if (!$response->successful()) {
                throw new RuntimeException('Drive-Ordnersuche fehlgeschlagen (HTTP ' . $response->status() . ').');
            }

            /** @var array{files?: list<array{id?: string}>} $data */
            $data = (array) $response->json();
            $found = (string) ($data['files'][0]['id'] ?? '');
            if ($found !== '') {
                $parentId = $found;

                continue;
            }

            if (!$create) {
                return null;
            }

            $created = $this->api->postJson($this->base . '/files?fields=id', [
                'name' => $segment,
                'mimeType' => self::FOLDER_MIME,
                'parents' => [$parentId],
            ]);
            if (!$created->successful()) {
                throw new RuntimeException('Drive-Ordneranlage fehlgeschlagen (HTTP ' . $created->status() . ').');
            }
            $parentId = (string) $created->json('id', '');
        }

        return $parentId;
    }
}
