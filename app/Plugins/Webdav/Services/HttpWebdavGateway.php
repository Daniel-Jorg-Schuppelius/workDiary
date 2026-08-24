<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HttpWebdavGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Services;

use App\Models\WebdavConnection;
use App\Plugins\Support\Mirror\RemoteFileGateway;
use App\Plugins\Support\PluginApiClient;
use App\Support\UrlSafety;
use RuntimeException;
use Throwable;

/**
 * WebDAV-Gateway über HTTP (Feature 058, MVP-127). Basic-Auth mit dem
 * verschlüsselten App-Passwort der Anbindung; generisches WebDAV (Nextcloud/
 * ownCloud als Referenz). Läuft über den {@see PluginApiClient} (api-toolkit
 * ≥ v2.9.2: WebDAV-Verben mit methodenbewusstem Retry); der injizierbare
 * Client macht den Adapter testbar (MockHandler-Transport). Rückgaben sind
 * bewusst schlicht (bool / ?string); die Fehlerbehandlung (Retry/Konflikt)
 * liegt im Spiegel-Service bzw. der Outbox.
 */
class HttpWebdavGateway implements RemoteFileGateway {
    public function __construct(
        private readonly PluginApiClient $http,
        private readonly WebdavConnection $connection,
    ) {
        // SSRF-Schutz: die org-konfigurierte Basis-URL muss öffentlich routbar
        // sein (kein Loopback/RFC1918/Metadata) — Whitebox-Befund 2026-07.
        if (! UrlSafety::isPubliclyRoutableHttpUrl((string) $connection->base_url)) {
            throw new RuntimeException('WebDAV base_url is not a publicly routable http(s) target.');
        }
    }

    public function ensureCollection(string $collectionPath): bool {
        $accumulated = '';
        foreach (explode('/', trim($collectionPath, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }
            $accumulated .= ($accumulated === '' ? '' : '/') . $segment;
            try {
                $response = $this->http->requestResponse('MKCOL', $this->connection->objectUrl($accumulated), [
                    'auth' => $this->auth(),
                ]);
            } catch (Throwable) {
                return false;
            }
            $code = $response->status();
            // 201 angelegt, 405 existiert bereits, 200/301 tolerant.
            if (! in_array($code, [200, 201, 301, 405], true)) {
                return false;
            }
        }

        return true;
    }

    public function putFile(string $path, string $contents, string $mime): bool {
        try {
            $response = $this->http->requestResponse('PUT', $this->connection->objectUrl($path), [
                'auth' => $this->auth(),
                'headers' => ['Content-Type' => $mime !== '' ? $mime : 'application/octet-stream'],
                'body' => $contents,
            ]);
        } catch (Throwable) {
            return false;
        }

        return $response->successful();
    }

    public function getFile(string $path): ?string {
        try {
            $response = $this->http->requestResponse('GET', $this->connection->objectUrl($path), [
                'auth' => $this->auth(),
            ]);
        } catch (Throwable) {
            return null;
        }

        if ($response->status() >= 400) {
            return null; // nicht vorhanden / Fehler
        }

        return $response->body();
    }

    public function remoteSignature(string $path): ?string {
        try {
            $response = $this->http->requestResponse('HEAD', $this->connection->objectUrl($path), [
                'auth' => $this->auth(),
            ]);
        } catch (Throwable) {
            return null;
        }

        if ($response->status() >= 400) {
            return null; // nicht vorhanden / Fehler
        }

        $etag = trim($response->header('ETag'), '"');
        if ($etag !== '') {
            return $etag;
        }

        // Fallback-Signatur ohne ETag.
        $signature = $response->header('Last-Modified') . '|' . $response->header('Content-Length');

        return $signature !== '|' ? $signature : null;
    }

    public function ping(): bool {
        try {
            // PROPFIND Depth:0 auf die Collection: 207 Multi-Status = erreichbar + Auth gültig.
            $response = $this->http->requestResponse('PROPFIND', rtrim($this->connection->base_url, '/'), [
                'auth' => $this->auth(),
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8'],
                'body' => '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>',
            ]);
        } catch (Throwable) {
            return false;
        }

        return $response->status() === 207;
    }

    /** @return array{0: string, 1: string} */
    private function auth(): array {
        return [$this->connection->username, $this->connection->app_password];
    }
}
