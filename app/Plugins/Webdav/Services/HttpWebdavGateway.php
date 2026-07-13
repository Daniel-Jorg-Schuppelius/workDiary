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
use App\Support\UrlSafety;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use Throwable;

/**
 * WebDAV-Gateway über HTTP (Feature 058, MVP-127). Basic-Auth mit dem
 * verschlüsselten App-Passwort der Anbindung; generisches WebDAV (Nextcloud/
 * ownCloud als Referenz). Ein injizierbarer Guzzle-Client macht den Adapter
 * testbar (MockHandler). Rückgaben sind bewusst schlicht (bool / ?string); die
 * Fehlerbehandlung (Retry/Konflikt) liegt im Spiegel-Service bzw. der Outbox.
 */
class HttpWebdavGateway implements RemoteFileGateway {
    public function __construct(
        private readonly ClientInterface $http,
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
                $response = $this->http->request('MKCOL', $this->connection->objectUrl($accumulated), [
                    'auth' => $this->auth(),
                    'http_errors' => false,
                ]);
            } catch (Throwable) {
                return false;
            }
            $code = $response->getStatusCode();
            // 201 angelegt, 405 existiert bereits, 200/301 tolerant.
            if (! in_array($code, [200, 201, 301, 405], true)) {
                return false;
            }
        }

        return true;
    }

    public function putFile(string $path, string $contents, string $mime): bool {
        try {
            $response = $this->http->request('PUT', $this->connection->objectUrl($path), [
                'auth' => $this->auth(),
                'headers' => ['Content-Type' => $mime !== '' ? $mime : 'application/octet-stream'],
                'body' => $contents,
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            return false;
        }

        $code = $response->getStatusCode();

        return $code >= 200 && $code < 300;
    }

    public function getFile(string $path): ?string {
        try {
            $response = $this->http->request('GET', $this->connection->objectUrl($path), [
                'auth' => $this->auth(),
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            return null;
        }

        if ($response->getStatusCode() >= 400) {
            return null; // nicht vorhanden / Fehler
        }

        return (string) $response->getBody();
    }

    public function remoteSignature(string $path): ?string {
        try {
            $response = $this->http->request('HEAD', $this->connection->objectUrl($path), [
                'auth' => $this->auth(),
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            return null;
        }

        if ($response->getStatusCode() >= 400) {
            return null; // nicht vorhanden / Fehler
        }

        $etag = trim($response->getHeaderLine('ETag'), '"');
        if ($etag !== '') {
            return $etag;
        }

        // Fallback-Signatur ohne ETag.
        $signature = $response->getHeaderLine('Last-Modified') . '|' . $response->getHeaderLine('Content-Length');

        return $signature !== '|' ? $signature : null;
    }

    public function ping(): bool {
        try {
            // PROPFIND Depth:0 auf die Collection: 207 Multi-Status = erreichbar + Auth gültig.
            $response = $this->http->request('PROPFIND', rtrim($this->connection->base_url, '/'), [
                'auth' => $this->auth(),
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8'],
                'body' => '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>',
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            return false;
        }

        return $response->getStatusCode() === 207;
    }

    /** @return array{0: string, 1: string} */
    private function auth(): array {
        return [$this->connection->username, $this->connection->app_password];
    }
}
