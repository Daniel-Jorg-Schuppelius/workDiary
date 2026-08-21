<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavBackupClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Api;

use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use App\Support\UrlSafety;
use CommonToolkit\Helper\Data\XmlHelper;
use GuzzleHttp\{Client as GuzzleClient, ClientInterface};
use Psr\Http\Message\{ResponseInterface, StreamInterface};
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * Generisches WebDAV-Backupziel (Feature 123, MVP-612).
 *
 * Bewusst anbieterneutral: Nextcloud, ownCloud, Synology, HiDrive, jeder
 * Apache mit `mod_dav`. Keine Chunk-Sonderwege — genau deshalb bleibt das
 * Nextcloud-Ziel daneben bestehen: Es nutzt Chunked Upload v2, den ein
 * generischer Server nicht kennt.
 *
 * Hochgeladen wird ausschließlich CIPHERTEXT; die clientseitige
 * Verschlüsselung der Backup-Pipeline bleibt unverändert. Der Zielserver
 * sieht nie Klartext — auch der eigene nicht.
 */
class WebdavBackupClient {
    private const DAV_NS = 'DAV:';

    private readonly string $base;

    private readonly string $username;

    private readonly string $password;

    private ClientInterface $http;

    public function __construct(
        BackupTargetConnection $connection,
        ?ClientInterface $http = null,
        private readonly bool $allowPrivateTargets = false,
    ) {
        $this->base = rtrim(trim((string) $connection->server_url), '/') . '/';
        $this->username = (string) $connection->username;
        $this->password = (string) $connection->access_token;
        $this->http = $http ?? new GuzzleClient(['timeout' => 120]);

        $this->assertAcceptable($this->base);
    }

    /**
     * Nur HTTPS, und ohne ausdrückliche Freigabe nur öffentlich routbare
     * Ziele (SSRF/DNS-Rebinding) — dieselbe Regel wie beim Nextcloud-Ziel.
     */
    private function assertAcceptable(#[SensitiveParameter] string $url): void {
        if (! str_starts_with(strtolower($url), 'https://')) {
            throw new RuntimeException('WebDAV backup URL must use https.');
        }
        if (! $this->allowPrivateTargets && ! UrlSafety::isPubliclyRoutableHttpUrl(rtrim($url, '/'))) {
            throw new RuntimeException('WebDAV backup URL is not a publicly routable https target.');
        }
    }

    public function host(): string {
        return (string) parse_url($this->base, PHP_URL_HOST);
    }

    public function account(): BackupAccount {
        if (! $this->ping()) {
            throw new RuntimeException('WebDAV-Anmeldung fehlgeschlagen (PROPFIND ohne 207).');
        }

        return new BackupAccount(
            externalId: $this->host() . '|' . $this->username,
            label: $this->username . ' @ ' . $this->host(),
        );
    }

    /** PROPFIND Depth:0 auf die Basis: 207 = erreichbar und Zugangsdaten gültig. */
    public function ping(): bool {
        try {
            $response = $this->send('PROPFIND', $this->base, [
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8'],
                'body' => $this->propfindBody('<d:resourcetype/>'),
            ]);
        } catch (Throwable) {
            return false;
        }

        return $response->getStatusCode() === 207;
    }

    /**
     * Speicherplatz, wenn der Server ihn liefert. Viele WebDAV-Server melden
     * die Quota-Properties nicht — dann bleibt es ehrlich bei „unbekannt"
     * statt einer geschätzten Zahl.
     *
     * @return array{total: int|null, used: int|null}
     */
    public function quota(): array {
        try {
            $response = $this->send('PROPFIND', $this->base, [
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8'],
                'body' => $this->propfindBody('<d:quota-available-bytes/><d:quota-used-bytes/>'),
            ]);
        } catch (Throwable) {
            return ['total' => null, 'used' => null];
        }
        if ($response->getStatusCode() !== 207) {
            return ['total' => null, 'used' => null];
        }

        $xml = (string) $response->getBody();
        $available = $this->firstInt($xml, '//d:quota-available-bytes');
        $used = $this->firstInt($xml, '//d:quota-used-bytes');

        return [
            'total' => ($available === null || $used === null) ? null : $available + $used,
            'used' => $used,
        ];
    }

    /** Legt den Backupbereich rekursiv an; idempotent (405 = existiert). */
    public function ensureFolder(string $path): string {
        $accumulated = '';
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }
            $accumulated .= ($accumulated === '' ? '' : '/') . $segment;
            $response = $this->send('MKCOL', $this->fileUrl($accumulated));
            $code = $response->getStatusCode();
            if (! in_array($code, [200, 201, 301, 405], true)) {
                throw new RuntimeException("WebDAV MKCOL failed for '{$accumulated}' (HTTP {$code}).");
            }
        }

        return trim($path, '/');
    }

    /**
     * Verbindungstest VOR dem Speichern: Ordner anlegen, Datei schreiben,
     * lesen und wieder löschen. Ein Backupziel, das erst im Ernstfall als
     * unbrauchbar auffällt, ist schlimmer als keins.
     */
    public function selfTest(string $path): void {
        $this->ensureFolder($path . '/.wd-selftest');
        $probe = $path . '/.wd-selftest/probe.txt';
        $payload = 'workdiary-selftest-' . bin2hex(random_bytes(8));

        $put = $this->send('PUT', $this->fileUrl($probe), [
            'headers' => ['Content-Type' => 'application/octet-stream'],
            'body' => $payload,
        ]);
        if ($put->getStatusCode() < 200 || $put->getStatusCode() >= 300) {
            throw new RuntimeException('WebDAV-Testdatei konnte nicht geschrieben werden (HTTP ' . $put->getStatusCode() . ').');
        }

        $get = $this->send('GET', $this->fileUrl($probe));
        if ((string) $get->getBody() !== $payload) {
            throw new RuntimeException('WebDAV-Testdatei kam verändert zurück.');
        }

        // Aufräumen gehört zum Test: Ein Ziel, das nicht löschen kann, taugt
        // nicht für die Retention.
        if (! $this->delete($probe)) {
            throw new RuntimeException('WebDAV-Testdatei konnte nicht gelöscht werden.');
        }
        $this->delete($path . '/.wd-selftest');
    }

    /** @return list<BackupRemoteObject> */
    public function listObjects(string $prefix): array {
        try {
            $response = $this->send('PROPFIND', $this->fileUrl($prefix), [
                'headers' => ['Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8'],
                'body' => $this->propfindBody('<d:getcontentlength/><d:getlastmodified/><d:resourcetype/>'),
            ]);
        } catch (Throwable) {
            return [];
        }
        if ($response->getStatusCode() === 404) {
            return []; // Prefix existiert (noch) nicht.
        }
        if ($response->getStatusCode() !== 207) {
            throw new RuntimeException('WebDAV PROPFIND failed (HTTP ' . $response->getStatusCode() . ').');
        }

        return $this->parseChildren((string) $response->getBody(), $prefix);
    }

    /**
     * Upload einer bereits verschlüsselten Datei als ein PUT und Verifikation
     * über die Remote-Größe. Kein Chunking: Das ist serverabhängig und über
     * WebDAV nicht einheitlich zu haben.
     */
    public function upload(string $localPath, string $remoteName): string {
        $remoteName = trim($remoteName, '/');
        $parent = str_contains($remoteName, '/') ? substr($remoteName, 0, (int) strrpos($remoteName, '/')) : '';
        if ($parent !== '') {
            $this->ensureFolder($parent);
        }

        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("WebDAV upload: local file '{$localPath}' is not readable.");
        }

        try {
            $response = $this->send('PUT', $this->fileUrl($remoteName), [
                'headers' => ['Content-Type' => 'application/octet-stream'],
                'body' => $handle,
            ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $code = $response->getStatusCode();
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException("WebDAV PUT failed (HTTP {$code}).");
        }

        $localSize = (int) filesize($localPath);
        $remoteSize = $this->contentLength($remoteName);
        if ($remoteSize !== $localSize) {
            throw new RuntimeException("WebDAV-Upload unvollständig: remote {$remoteSize} B, lokal {$localSize} B.");
        }

        return $remoteName;
    }

    public function download(string $remoteRef): StreamInterface {
        $response = $this->send('GET', $this->fileUrl($remoteRef));
        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('WebDAV GET failed (HTTP ' . $response->getStatusCode() . ').');
        }

        return $response->getBody();
    }

    /** Löscht ein eigenes Objekt; 404 gilt als bereits gelöscht (idempotent). */
    public function delete(string $remoteRef): bool {
        $response = $this->send('DELETE', $this->fileUrl($remoteRef));
        $code = $response->getStatusCode();

        return $code === 404 || ($code >= 200 && $code < 300);
    }

    // ── Bausteine ───────────────────────────────────────────────────────

    private function fileUrl(string $path): string {
        $segments = array_map(
            'rawurlencode',
            array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s): bool => $s !== '')),
        );

        return $this->base . implode('/', $segments);
    }

    private function contentLength(string $path): int {
        $response = $this->send('HEAD', $this->fileUrl($path));
        if ($response->getStatusCode() >= 400) {
            return -1;
        }

        return (int) $response->getHeaderLine('Content-Length');
    }

    private function propfindBody(string $props): string {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:"><d:prop>' . $props . '</d:prop></d:propfind>';
    }

    private function firstInt(string $xml, string $xpath): ?int {
        $value = XmlHelper::xpathFirst($xml, $xpath, ['d' => self::DAV_NS]);
        if ($value === null || trim($value) === '') {
            return null;
        }
        $number = (int) trim($value);

        // -3 = „unbegrenzt", -1/-2 = unbekannt (RFC 4331).
        return $number < 0 ? null : $number;
    }

    /** @return list<BackupRemoteObject> */
    private function parseChildren(string $xml, string $prefix): array {
        $requested = trim($prefix, '/');
        $out = [];

        foreach (XmlHelper::xpathNodes($xml, '//d:response', ['d' => self::DAV_NS]) as $node) {
            $hrefNode = $node->getElementsByTagNameNS(self::DAV_NS, 'href')->item(0);
            if ($hrefNode === null) {
                continue;
            }
            $path = $this->hrefToPath((string) $hrefNode->nodeValue);
            if ($path === null || $path === $requested) {
                continue; // Selbst-Eintrag der Collection.
            }
            if ($node->getElementsByTagNameNS(self::DAV_NS, 'collection')->length > 0) {
                continue; // Ordner sind keine Backup-Objekte.
            }

            $size = $node->getElementsByTagNameNS(self::DAV_NS, 'getcontentlength')->item(0);
            $modified = $node->getElementsByTagNameNS(self::DAV_NS, 'getlastmodified')->item(0);

            $out[] = new BackupRemoteObject(
                ref: $path,
                name: basename($path),
                size: $size === null ? 0 : (int) $size->nodeValue,
                modifiedAt: ($modified === null || trim((string) $modified->nodeValue) === '')
                    ? null
                    : (string) $modified->nodeValue,
            );
        }

        return $out;
    }

    /** href → Pfad relativ zur Basis-URL; fremde hrefs ergeben null. */
    private function hrefToPath(string $href): ?string {
        $href = rawurldecode(trim($href));
        $basePath = (string) parse_url($this->base, PHP_URL_PATH);

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $href = (string) parse_url($href, PHP_URL_PATH);
        }
        if ($basePath !== '' && ! str_starts_with($href, $basePath)) {
            return null;
        }

        return trim(substr($href, strlen($basePath)), '/');
    }

    /** @param array<string, mixed> $options */
    private function send(string $method, string $url, array $options = []): ResponseInterface {
        $options['auth'] = [$this->username, $this->password];
        $options['http_errors'] = false;

        try {
            return $this->http->request($method, $url, $options);
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie URL oder Passwort in der Meldung.
            throw new RuntimeException('WebDAV request failed (' . class_basename($e) . ').', 0, $e);
        }
    }
}
