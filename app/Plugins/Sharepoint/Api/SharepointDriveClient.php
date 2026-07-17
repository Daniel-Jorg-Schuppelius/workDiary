<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointDriveClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Sharepoint\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\SharepointConnection;
use App\Plugins\Sharepoint\{SharepointConfig, SharepointPlugin};
use App\Plugins\Support\{ConnectionTokenStore, PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\Mirror\RemoteFileGateway;
use RuntimeException;
use Throwable;

/**
 * Microsoft-Graph-Drive-Gateway der SharePoint-Ablage (MVP-330, Bauturbo A10)
 * auf dem `php-api-toolkit`-Fundament: OAuth2-Bearer über den org-gebundenen
 * {@see ConnectionTokenStore} inkl. transparentem Refresh (401 ⇒ Refresh ⇒ genau
 * ein Retry im ClientAbstract).
 *
 * - Kleine Dateien (< 4 MB): `PUT /drives/{id}/root:/{pfad}:/content`.
 * - Große Dateien: `createUploadSession` + Chunk-PUTs (Vielfache von
 *   320 KiB) an die Session-URL — laut Graph-Doku OHNE Authorization-Header
 *   (die Session-URL ist selbst-autorisierend), daher über einen zweiten,
 *   auth-losen Client.
 * - Konflikterkennung über die Server-Signatur (`cTag` — ändert sich nur bei
 *   Inhaltsänderung; Fallback `eTag`), Semantik wie das WebDAV-Gateway.
 * - Site-/Bibliotheks-Auswahl: `GET /sites?search=` + `GET /sites/{id}/drives`.
 *
 * Fehlersemantik wie WebDAV-Gateway: Rückgaben bewusst schlicht
 * (bool / ?string); Retry/Konflikt liegen im Spiegel-Service bzw. der Outbox.
 */
class SharepointDriveClient implements RemoteFileGateway {
    /** Graph-Grenze für den einfachen Upload; darüber Upload-Session (Chunks). */
    public const SIMPLE_UPLOAD_LIMIT = 4 * 1024 * 1024;

    /** Chunk-Größe der Upload-Session: Vielfaches von 320 KiB (Graph-Vorgabe). */
    public const CHUNK_SIZE = 10 * 320 * 1024;

    private PluginApiClient $api;

    /** Auth-loser Client für Chunk-PUTs an die selbst-autorisierende Session-URL. */
    private PluginApiClient $uploadApi;

    private string $base;

    public function __construct(private readonly SharepointConnection $connection) {
        $this->base = SharepointConfig::resolve()['api_base'];
        $factory = app(PluginHttpFactory::class);
        $this->api = $factory->client(SharepointPlugin::ID, $this->base);
        $this->uploadApi = $factory->client(SharepointPlugin::ID, $this->base);

        // Grant nur bei vorhandener Installation-Konfiguration — ohne ihn
        // bleibt das Bearer-Token nutzbar, nur ohne Refresh-Möglichkeit.
        $grant = SharepointConfig::isConfigured() ? app(SharepointOAuth::class)->grant() : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($this->connection), $grant));
    }

    /** Stellt den Zielordner segmentweise sicher (POST children; 409 = existiert). */
    public function ensureCollection(string $collectionPath): bool {
        $accumulated = '';
        foreach (explode('/', trim($collectionPath, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }
            $parentUrl = $accumulated === ''
                ? $this->driveUrl() . '/root/children'
                : $this->driveUrl() . '/root:/' . $this->encodePath($accumulated) . ':/children';

            try {
                $response = $this->api->postJson($parentUrl, [
                    'name' => $segment,
                    'folder' => (object) [],
                    '@microsoft.graph.conflictBehavior' => 'fail',
                ]);
            } catch (Throwable) {
                return false;
            }
            // 201 angelegt, 409 existiert bereits (conflictBehavior=fail).
            if (! $response->successful() && $response->status() !== 409) {
                return false;
            }
            $accumulated .= ($accumulated === '' ? '' : '/') . $segment;
        }

        return true;
    }

    public function putFile(string $path, string $contents, string $mime): bool {
        return strlen($contents) <= self::SIMPLE_UPLOAD_LIMIT
            ? $this->putSmallFile($path, $contents, $mime)
            : $this->putLargeFile($path, $contents);
    }

    public function getFile(string $path): ?string {
        try {
            $response = $this->api->getResponse($this->itemUrl($path) . ':/content');
        } catch (Throwable) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    public function remoteSignature(string $path): ?string {
        try {
            $response = $this->api->getResponse($this->itemUrl($path), ['$select' => 'cTag,eTag']);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null; // nicht vorhanden / Fehler
        }

        // cTag ändert sich nur bei Inhaltsänderung (eTag auch bei Metadaten).
        $cTag = trim((string) $response->json('cTag', ''));
        if ($cTag !== '') {
            return $cTag;
        }
        $eTag = trim((string) $response->json('eTag', ''));

        return $eTag !== '' ? $eTag : null;
    }

    /** Liveness/Auth-Check: gewählte Bibliothek (bzw. Site) erreichbar. */
    public function ping(): bool {
        try {
            if (trim((string) $this->connection->drive_id) !== '') {
                return $this->api->getResponse($this->driveUrl(), ['$select' => 'id'])->successful();
            }
            if (trim((string) $this->connection->site_id) !== '') {
                return $this->api->getResponse($this->base . '/sites/' . rawurlencode((string) $this->connection->site_id), ['$select' => 'id'])->successful();
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Site-Suche für die Admin-Auswahl (`GET /sites?search=`); bei
     * `Sites.Selected` liefert Graph nur die granteten Sites.
     *
     * @return list<array{id: string, name: string, url: string}>
     */
    public function listSites(string $search): array {
        $response = $this->api->getResponse($this->base . '/sites', ['search' => $search, '$select' => 'id,displayName,webUrl']);
        if (! $response->successful()) {
            // Nur Statuscode + Pfad — nie Payload/Token in Fehlermeldungen.
            throw new RuntimeException(sprintf('Microsoft Graph /sites?search antwortete mit HTTP %d.', $response->status()));
        }

        $sites = [];
        foreach ((array) $response->json('value', []) as $row) {
            if (is_array($row) && is_string($row['id'] ?? null) && $row['id'] !== '') {
                $sites[] = [
                    'id' => $row['id'],
                    'name' => (string) ($row['displayName'] ?? $row['id']),
                    'url' => (string) ($row['webUrl'] ?? ''),
                ];
            }
        }

        return $sites;
    }

    /**
     * Einzelne Site auflösen (`GET /sites/{id}`) — serverseitige Validierung
     * der Admin-Auswahl (kein Unterschieben fremder Site-IDs ohne Zugriff).
     *
     * @return array{id: string, name: string}|null
     */
    public function getSite(string $siteId): ?array {
        try {
            $response = $this->api->getResponse($this->base . '/sites/' . rawurlencode($siteId), ['$select' => 'id,displayName']);
        } catch (Throwable) {
            return null;
        }

        $id = $response->json('id');
        if (! $response->successful() || ! is_string($id) || $id === '') {
            return null;
        }

        return ['id' => $id, 'name' => (string) ($response->json('displayName') ?: $id)];
    }

    /**
     * Dokumentbibliotheken (Drives) einer Site (`GET /sites/{id}/drives`).
     *
     * @return list<array{id: string, name: string}>
     */
    public function listDrives(string $siteId): array {
        $response = $this->api->getResponse($this->base . '/sites/' . rawurlencode($siteId) . '/drives', ['$select' => 'id,name']);
        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Microsoft Graph /sites/{id}/drives antwortete mit HTTP %d.', $response->status()));
        }

        $drives = [];
        foreach ((array) $response->json('value', []) as $row) {
            if (is_array($row) && is_string($row['id'] ?? null) && $row['id'] !== '') {
                $drives[] = ['id' => $row['id'], 'name' => (string) ($row['name'] ?? $row['id'])];
            }
        }

        return $drives;
    }

    /** Einfacher Upload (< 4 MB): PUT auf den Pfad, überschreibt. */
    private function putSmallFile(string $path, string $contents, string $mime): bool {
        try {
            $response = $this->api->requestResponse('put', $this->itemUrl($path) . ':/content', [
                'headers' => ['Content-Type' => $mime !== '' ? $mime : 'application/octet-stream'],
                'body' => $contents,
            ]);
        } catch (Throwable) {
            return false;
        }

        return $response->successful();
    }

    /** Großer Upload: createUploadSession + Chunk-PUTs (Content-Range, ohne Auth-Header). */
    private function putLargeFile(string $path, string $contents): bool {
        try {
            $session = $this->api->postJson($this->itemUrl($path) . ':/createUploadSession', [
                'item' => ['@microsoft.graph.conflictBehavior' => 'replace'],
            ]);
        } catch (Throwable) {
            return false;
        }
        $uploadUrl = (string) $session->json('uploadUrl', '');
        if (! $session->successful() || $uploadUrl === '') {
            return false;
        }

        $total = strlen($contents);
        for ($offset = 0; $offset < $total; $offset += self::CHUNK_SIZE) {
            $chunk = substr($contents, $offset, self::CHUNK_SIZE);
            $last = $offset + strlen($chunk) - 1;

            try {
                // Session-URL ist selbst-autorisierend — Graph verlangt die
                // Chunk-PUTs ausdrücklich OHNE Authorization-Header.
                $response = $this->uploadApi->requestResponse('put', $uploadUrl, [
                    'headers' => [
                        'Content-Length' => (string) strlen($chunk),
                        'Content-Range' => sprintf('bytes %d-%d/%d', $offset, $last, $total),
                    ],
                    'body' => $chunk,
                ]);
            } catch (Throwable) {
                return false;
            }
            // Zwischenchunks: 202 Accepted; letzter Chunk: 200/201 mit Item.
            if (! $response->successful() && $response->status() !== 202) {
                return false;
            }
        }

        return true;
    }

    private function driveUrl(): string {
        return $this->base . '/drives/' . rawurlencode((string) $this->connection->drive_id);
    }

    /** Pfad-adressiertes Item `/drives/{id}/root:/{pfad}` (Segmente URL-kodiert). */
    private function itemUrl(string $path): string {
        return $this->driveUrl() . '/root:/' . $this->encodePath($path);
    }

    private function encodePath(string $path): string {
        return implode('/', array_map('rawurlencode', explode('/', trim($path, '/'))));
    }
}
