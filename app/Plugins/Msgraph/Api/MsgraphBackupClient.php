<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphBackupClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use App\Plugins\Support\{ConnectionTokenStore, PluginHttpFactory};
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Microsoft-Graph-Gateway des Cloud-BACKUPZIELS (Feature 017 Phase 32,
 * MVP-363): OneDrive des bestätigten Integrationskontos (`/me/drive`),
 * Teil-Uploads über `createUploadSession` + Chunk-PUTs (Muster
 * {@see \App\Plugins\Sharepoint\Api\SharepointDriveClient}), Remote-Größe
 * wird nach dem letzten Chunk verifiziert. List/Delete nur unterhalb des
 * übergebenen Prefix (eigener Backupbereich).
 */
class MsgraphBackupClient {
    /** Chunk-Größe: Vielfaches von 320 KiB (Graph-Anforderung) — 7,5 MiB. */
    private const CHUNK_SIZE = 7_864_320;

    private \App\Plugins\Support\PluginApiClient $api;

    /** Session-URL-PUTs verlangt Graph ausdrücklich OHNE Authorization-Header. */
    private \App\Plugins\Support\PluginApiClient $uploadApi;

    private string $base;

    public function __construct(BackupTargetConnection $connection) {
        $config = MsgraphConfig::resolve();
        $factory = app(PluginHttpFactory::class);
        $this->base = $config['api_base'];
        $this->api = $factory->client(MsgraphPlugin::ID, $this->base);
        $this->uploadApi = $factory->client(MsgraphPlugin::ID, $this->base);

        $grant = MsgraphConfig::isConfigured() ? app(MsgraphBackupOAuth::class)->grant() : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($connection, 'granted_scopes', scopeAsArray: true), $grant));
    }

    public function account(): BackupAccount {
        $response = $this->api->getResponse($this->base . '/me');
        if (!$response->successful()) {
            throw new RuntimeException('Graph /me fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{id?: string, displayName?: string, mail?: string, userPrincipalName?: string} $data */
        $data = (array) $response->json();

        return new BackupAccount(
            externalId: (string) ($data['id'] ?? ''),
            label: trim((string) ($data['displayName'] ?? '') . ' <' . (string) ($data['mail'] ?? $data['userPrincipalName'] ?? '') . '>'),
        );
    }

    /** @return array{total: int|null, used: int|null} */
    public function quota(): array {
        $response = $this->api->getResponse($this->base . '/me/drive', ['$select' => 'quota']);
        if (!$response->successful()) {
            throw new RuntimeException('Graph /me/drive fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{quota?: array{total?: int, used?: int}} $data */
        $data = (array) $response->json();

        return [
            'total' => isset($data['quota']['total']) ? (int) $data['quota']['total'] : null,
            'used' => isset($data['quota']['used']) ? (int) $data['quota']['used'] : null,
        ];
    }

    /** Legt den Pfad segmentweise an (idempotent); Referenz ist die Item-ID. */
    public function ensureFolder(string $path): string {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $current = '';
        $itemId = '';

        foreach ($segments as $segment) {
            $current = $current === '' ? $segment : $current . '/' . $segment;
            $lookup = $this->api->getResponse($this->itemUrl($current), ['$select' => 'id']);
            if ($lookup->successful()) {
                $itemId = (string) $lookup->json('id', '');

                continue;
            }
            if ($lookup->status() !== 404) {
                throw new RuntimeException('Graph-Ordnerprüfung fehlgeschlagen (HTTP ' . $lookup->status() . ').');
            }

            $parentUrl = str_contains($current, '/')
                ? $this->itemUrl(dirname($current)) . ':/children'
                : $this->base . '/me/drive/root/children';
            $created = $this->api->postJson($parentUrl, [
                'name' => $segment,
                'folder' => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'fail',
            ]);
            if ($created->status() === 409) {
                $retry = $this->api->getResponse($this->itemUrl($current), ['$select' => 'id']);
                $itemId = (string) $retry->json('id', '');

                continue;
            }
            if (!$created->successful()) {
                throw new RuntimeException('Graph-Ordneranlage fehlgeschlagen (HTTP ' . $created->status() . ').');
            }
            $itemId = (string) $created->json('id', '');
        }

        return $itemId;
    }

    /**
     * Direkte Einträge unterhalb des Prefix (eigener Backupbereich).
     *
     * @return list<BackupRemoteObject>
     */
    public function listObjects(string $prefix): array {
        $objects = [];
        $url = $this->itemUrl(trim($prefix, '/')) . ':/children';
        $query = ['$select' => 'id,name,size,folder,lastModifiedDateTime', '$top' => 200];

        do {
            $response = $this->api->getResponse($url, $query);
            if ($response->status() === 404) {
                return []; // Prefix existiert (noch) nicht.
            }
            if (!$response->successful()) {
                throw new RuntimeException('Graph-Listing fehlgeschlagen (HTTP ' . $response->status() . ').');
            }

            /** @var array{value?: list<array<string, mixed>>, "@odata.nextLink"?: string} $data */
            $data = (array) $response->json();
            foreach ((array) ($data['value'] ?? []) as $row) {
                $objects[] = new BackupRemoteObject(
                    ref: (string) ($row['id'] ?? ''),
                    name: (string) ($row['name'] ?? ''),
                    size: (int) ($row['size'] ?? 0),
                    modifiedAt: isset($row['lastModifiedDateTime']) ? (string) $row['lastModifiedDateTime'] : null,
                );
            }
            $url = (string) ($data['@odata.nextLink'] ?? '');
            $query = []; // nextLink trägt die Parameter bereits
        } while ($url !== '');

        return $objects;
    }

    /** Resumable Upload einer lokalen Datei; verifiziert die Remote-Größe. */
    public function uploadPart(string $localPath, string $remoteName): string {
        $size = filesize($localPath);
        $in = @fopen($localPath, 'rb');
        if ($in === false || $size === false) {
            throw new RuntimeException("Backup-Teil nicht lesbar: {$localPath}");
        }

        try {
            $session = $this->api->postJson($this->itemUrl(trim($remoteName, '/')) . ':/createUploadSession', [
                'item' => ['@microsoft.graph.conflictBehavior' => 'replace'],
            ]);
            $uploadUrl = (string) $session->json('uploadUrl', '');
            if (!$session->successful() || $uploadUrl === '') {
                throw new RuntimeException('Graph createUploadSession fehlgeschlagen (HTTP ' . $session->status() . ').');
            }

            $offset = 0;
            $itemId = '';
            $remoteSize = -1;
            while (!feof($in)) {
                $chunk = fread($in, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new RuntimeException("Lesefehler in {$localPath}.");
                }
                if ($chunk === '') {
                    continue;
                }
                $last = $offset + strlen($chunk) - 1;
                // Session-URL ist selbst-autorisierend — Chunk-PUTs OHNE Auth-Header.
                $response = $this->uploadApi->requestResponse('put', $uploadUrl, [
                    'headers' => [
                        'Content-Length' => (string) strlen($chunk),
                        'Content-Range' => sprintf('bytes %d-%d/%d', $offset, $last, $size),
                    ],
                    'body' => $chunk,
                ]);
                // Zwischenchunks: 202 Accepted; letzter Chunk: 200/201 mit Item.
                if (!$response->successful() && $response->status() !== 202) {
                    throw new RuntimeException('Graph-Chunk-Upload fehlgeschlagen (HTTP ' . $response->status() . ').');
                }
                if ($response->successful() && $response->json('id') !== null) {
                    $itemId = (string) $response->json('id');
                    $remoteSize = (int) $response->json('size', -1);
                }
                $offset += strlen($chunk);
            }

            if ($itemId === '' || $remoteSize !== (int) $size) {
                throw new RuntimeException("Graph-Upload unvollständig: remote {$remoteSize} B, lokal {$size} B.");
            }

            return $itemId;
        } finally {
            fclose($in);
        }
    }

    public function download(string $remoteRef): StreamInterface {
        $response = $this->api->requestResponse('get', $this->base . '/me/drive/items/' . rawurlencode($remoteRef) . '/content');
        if (!$response->successful()) {
            throw new RuntimeException('Graph-Download fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $response->toPsrResponse()->getBody();
    }

    /** Löscht ein EIGENES Item (Datei oder Ordner rekursiv); idempotent. */
    public function delete(string $remoteRef): bool {
        $response = $this->api->deleteResponse($this->base . '/me/drive/items/' . rawurlencode($remoteRef));
        if ($response->status() === 404) {
            return true;
        }
        if (!$response->successful()) {
            throw new RuntimeException('Graph-Löschung fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return true;
    }

    /** Pfad-adressiertes Item `/me/drive/root:/{pfad}` (Segmente URL-kodiert). */
    private function itemUrl(string $path): string {
        $encoded = implode('/', array_map('rawurlencode', explode('/', trim($path, '/'))));

        return $this->base . '/me/drive/root:/' . $encoded;
    }
}
