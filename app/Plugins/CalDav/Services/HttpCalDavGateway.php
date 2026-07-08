<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HttpCalDavGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

use App\Models\CalDavConnection;
use App\Plugins\CalDav\Contracts\CalDavGateway;
use App\Support\UrlSafety;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use Throwable;

/**
 * CalDAV-Gateway über HTTP (Feature 058, MVP-126). Basic-Auth mit dem
 * verschlüsselten App-Passwort der Anbindung; RFC-4791-konform (Nextcloud/
 * ownCloud als Referenz, generisch). Ein injizierbarer Guzzle-Client macht den
 * Adapter testbar (MockHandler). Fehler werden zu `false` — der Publisher zählt
 * sie als `failed` und versucht sie beim nächsten Lauf erneut.
 */
class HttpCalDavGateway implements CalDavGateway {
    public function __construct(
        private readonly ClientInterface $http,
        private readonly CalDavConnection $connection,
    ) {
        // SSRF-Schutz: die org-konfigurierte Basis-URL muss öffentlich routbar
        // sein (kein Loopback/RFC1918/Metadata) — Whitebox-Befund 2026-07.
        if (! UrlSafety::isPubliclyRoutableHttpUrl((string) $connection->base_url)) {
            throw new RuntimeException('CalDAV base_url is not a publicly routable http(s) target.');
        }
    }

    public function putObject(string $objectName, string $ics): bool {
        try {
            $response = $this->http->request('PUT', $this->connection->objectUrl($objectName), [
                'auth' => [$this->connection->username, $this->connection->app_password],
                'headers' => ['Content-Type' => 'text/calendar; charset=utf-8'],
                'body' => $ics,
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            return false;
        }

        return $this->isSuccess($response->getStatusCode());
    }

    public function deleteObject(string $objectName): bool {
        try {
            $response = $this->http->request('DELETE', $this->connection->objectUrl($objectName), [
                'auth' => [$this->connection->username, $this->connection->app_password],
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            return false;
        }

        // 404 = bereits entfernt → idempotenter Erfolg.
        return $this->isSuccess($response->getStatusCode()) || $response->getStatusCode() === 404;
    }

    public function ping(): bool {
        try {
            // PROPFIND Depth:0 auf die Collection: 207 Multi-Status = erreichbar + Auth gültig.
            $response = $this->http->request('PROPFIND', rtrim($this->connection->base_url, '/') . '/' . trim($this->connection->calendar_path, '/'), [
                'auth' => [$this->connection->username, $this->connection->app_password],
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8'],
                'body' => '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>',
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            return false;
        }

        return $response->getStatusCode() === 207;
    }

    private function isSuccess(int $status): bool {
        return $status >= 200 && $status < 300;
    }
}
