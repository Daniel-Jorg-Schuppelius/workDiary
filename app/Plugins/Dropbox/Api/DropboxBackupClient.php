<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DropboxBackupClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dropbox\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Dropbox\{DropboxConfig, DropboxPlugin};
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use App\Plugins\Support\PluginHttpFactory;
use App\Services\Backup\BackupTokenStore;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Dropbox-Gateway des Cloud-BACKUPZIELS (Feature 017 Phase 32, MVP-363):
 * `upload_session/start|append_v2|finish` für resumable Teil-Uploads,
 * Remote-Größe wird nach dem Finish verifiziert. Arbeitet ausschließlich im
 * eigenen Backupbereich (App-Folder-Registrierung empfohlen); List/Delete
 * nur unterhalb des übergebenen Prefix. Getrennt vom Intake-Client
 * ({@see DropboxClient}) — eigene Verbindung, eigene Scopes.
 */
class DropboxBackupClient {
    /** 8 MiB je Append — Dropbox erlaubt bis 150 MB je Request. */
    private const CHUNK_SIZE = 8_388_608;

    private \App\Plugins\Support\PluginApiClient $api;

    private \App\Plugins\Support\PluginApiClient $content;

    private string $apiBase;

    private string $contentBase;

    public function __construct(BackupTargetConnection $connection) {
        $config = DropboxConfig::resolve();
        $factory = app(PluginHttpFactory::class);

        // Vollqualifizierte URLs je Request (SevDesk-Muster): Guzzle-base_uri
        // würde bei führendem Slash den Basis-Pfad (/2) verwerfen.
        $this->apiBase = rtrim($config['api_base'], '/');
        $this->contentBase = rtrim($config['content_base'], '/');
        $this->api = $factory->client(DropboxPlugin::ID, $this->apiBase);
        $this->content = $factory->client(DropboxPlugin::ID, $this->contentBase);

        $grant = DropboxConfig::isConfigured() ? app(DropboxBackupOAuth::class)->grant() : null;
        $store = new BackupTokenStore($connection);
        $this->api->setAuthentication(new OAuth2BearerAuthentication($store, $grant));
        $this->content->setAuthentication(new OAuth2BearerAuthentication($store, $grant));
    }

    public function account(): BackupAccount {
        $response = $this->api->postJson($this->apiBase . '/users/get_current_account');
        if (!$response->successful()) {
            throw new RuntimeException('Dropbox get_current_account fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{account_id?: string, email?: string, name?: array{display_name?: string}} $data */
        $data = (array) $response->json();

        return new BackupAccount(
            externalId: (string) ($data['account_id'] ?? ''),
            label: trim((string) ($data['name']['display_name'] ?? '') . ' <' . (string) ($data['email'] ?? '') . '>'),
        );
    }

    /** @return array{total: int|null, used: int|null} */
    public function quota(): array {
        $response = $this->api->postJson($this->apiBase . '/users/get_space_usage');
        if (!$response->successful()) {
            throw new RuntimeException('Dropbox get_space_usage fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{used?: int, allocation?: array{allocated?: int}} $data */
        $data = (array) $response->json();

        return [
            'total' => isset($data['allocation']['allocated']) ? (int) $data['allocation']['allocated'] : null,
            'used' => isset($data['used']) ? (int) $data['used'] : null,
        ];
    }

    /** Legt den Ordner an (idempotent); Referenz ist der Pfad. */
    public function ensureFolder(string $path): string {
        $target = '/' . trim($path, '/');
        $response = $this->api->postJson($this->apiBase . '/files/create_folder_v2', [
            'path' => $target,
            'autorename' => false,
        ]);

        // 409 conflict/folder = existiert bereits — idempotent OK.
        if (!$response->successful() && !($response->status() === 409 && str_contains((string) $response->body(), 'conflict'))) {
            throw new RuntimeException('Dropbox create_folder fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $target;
    }

    /**
     * Direkte Einträge unterhalb des Prefix (eigener Backupbereich).
     *
     * @return list<BackupRemoteObject>
     */
    public function listObjects(string $prefix): array {
        $path = '/' . trim($prefix, '/');
        $objects = [];
        $cursor = null;

        do {
            $response = $cursor === null
                ? $this->api->postJson($this->apiBase . '/files/list_folder', ['path' => $path, 'recursive' => false])
                : $this->api->postJson($this->apiBase . '/files/list_folder/continue', ['cursor' => $cursor]);

            if ($response->status() === 409 && str_contains((string) $response->body(), 'not_found')) {
                return []; // Prefix existiert (noch) nicht.
            }
            if (!$response->successful()) {
                throw new RuntimeException('Dropbox list_folder fehlgeschlagen (HTTP ' . $response->status() . ').');
            }

            /** @var array{entries?: list<array<string, mixed>>, cursor?: string, has_more?: bool} $data */
            $data = (array) $response->json();
            foreach ((array) ($data['entries'] ?? []) as $entry) {
                $tag = (string) ($entry['.tag'] ?? '');
                if ($tag !== 'file' && $tag !== 'folder') {
                    continue;
                }
                $displayPath = (string) ($entry['path_display'] ?? '');
                $objects[] = new BackupRemoteObject(
                    ref: $displayPath,
                    name: (string) ($entry['name'] ?? basename($displayPath)),
                    size: (int) ($entry['size'] ?? 0),
                    modifiedAt: isset($entry['server_modified']) ? (string) $entry['server_modified'] : null,
                );
            }
            $cursor = (string) ($data['cursor'] ?? '');
        } while ((bool) ($data['has_more'] ?? false) && $cursor !== '');

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
            $start = $this->contentCall('/files/upload_session/start', ['close' => false], '');
            $sessionId = (string) $start->json('session_id', '');
            if (!$start->successful() || $sessionId === '') {
                throw new RuntimeException('Dropbox upload_session/start fehlgeschlagen (HTTP ' . $start->status() . ').');
            }

            $offset = 0;
            while (!feof($in)) {
                $chunk = fread($in, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new RuntimeException("Lesefehler in {$localPath}.");
                }
                if ($chunk === '') {
                    continue;
                }
                $append = $this->contentCall('/files/upload_session/append_v2', [
                    'cursor' => ['session_id' => $sessionId, 'offset' => $offset],
                    'close' => false,
                ], $chunk);
                if (!$append->successful()) {
                    throw new RuntimeException('Dropbox upload_session/append fehlgeschlagen (HTTP ' . $append->status() . ').');
                }
                $offset += strlen($chunk);
            }

            $finish = $this->contentCall('/files/upload_session/finish', [
                'cursor' => ['session_id' => $sessionId, 'offset' => $offset],
                'commit' => ['path' => '/' . ltrim($remoteName, '/'), 'mode' => 'overwrite', 'mute' => true],
            ], '');
            if (!$finish->successful()) {
                throw new RuntimeException('Dropbox upload_session/finish fehlgeschlagen (HTTP ' . $finish->status() . ').');
            }

            $remoteSize = (int) $finish->json('size', -1);
            if ($remoteSize !== (int) $size) {
                throw new RuntimeException("Dropbox-Upload unvollständig: remote {$remoteSize} B, lokal {$size} B.");
            }

            return (string) ($finish->json('path_display') ?? '/' . ltrim($remoteName, '/'));
        } finally {
            fclose($in);
        }
    }

    public function download(string $remoteRef): StreamInterface {
        $response = $this->content->requestResponse('POST', $this->contentBase . '/files/download', [
            'headers' => [
                'Dropbox-API-Arg' => json_encode(['path' => $remoteRef], JSON_THROW_ON_ERROR),
                // Dropbox verlangt einen leeren Body ohne JSON-Content-Type.
                'Content-Type' => 'text/plain; charset=dropbox-cors-hack',
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Dropbox-Download fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $response->toPsrResponse()->getBody();
    }

    /** Löscht ein EIGENES Objekt (Datei oder Ordner rekursiv); idempotent. */
    public function delete(string $remoteRef): bool {
        $response = $this->api->postJson($this->apiBase . '/files/delete_v2', ['path' => $remoteRef]);
        if ($response->status() === 409 && str_contains((string) $response->body(), 'not_found')) {
            return true;
        }
        if (!$response->successful()) {
            throw new RuntimeException('Dropbox delete fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return true;
    }

    /**
     * Content-Endpunkt-Aufruf: Argumente im Dropbox-API-Arg-Header,
     * Payload als Oktett-Body.
     *
     * @param array<string, mixed> $args
     */
    private function contentCall(string $path, array $args, string $body): \Illuminate\Http\Client\Response {
        return $this->content->requestResponse('POST', $this->contentBase . $path, [
            'headers' => [
                'Dropbox-API-Arg' => json_encode($args, JSON_THROW_ON_ERROR),
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $body,
        ]);
    }
}
