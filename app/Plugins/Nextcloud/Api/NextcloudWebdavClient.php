<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudWebdavClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud\Api;

use App\Support\UrlSafety;
use CommonToolkit\Helper\Data\XmlHelper;
use DOMElement;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * WebDAV-Transport für Nextcloud (Feature 080 MVP-382 / Feature 017 MVP-383).
 * Gemeinsame Basis für lesenden Dokumenteingang UND schreibendes Backupziel —
 * je Verbindung mit Server-URL, Nutzer und verschlüsseltem App-Passwort
 * (Basic-Auth, NIE das reguläre Benutzerpasswort). Baut auf den WebDAV-Mustern
 * aus {@see \App\Plugins\Webdav\Services\HttpWebdavGateway} auf, ergänzt aber
 * die für den Import/das Backup nötigen Operationen: PROPFIND-Listing mit
 * `oc:fileid`/ETag, Quota, resumable Chunked-Upload v2 und rekursives MKCOL.
 *
 * Ein injizierbarer Guzzle-Client macht den Transport ohne echten HTTP-Verkehr
 * testbar (MockHandler). PROPFIND-Multistatus wird über den namespaced-XPath des
 * gemeinsamen {@see XmlHelper} geparst (toolkit-first) — der generische
 * `xmlToArray` scheitert an den DAV:/oc:-Präfixen.
 */
class NextcloudWebdavClient {
    private const DAV_NS = 'DAV:';

    private const OC_NS = 'http://owncloud.org/ns';

    private readonly string $server;

    public function __construct(
        private readonly ClientInterface $http,
        string $serverUrl,
        private readonly string $username,
        #[SensitiveParameter] private readonly string $appPassword,
        private readonly bool $allowPrivateTargets = false,
        private readonly int $chunkSize = 10_485_760,
    ) {
        $this->server = rtrim(trim($serverUrl), '/');

        // Nur HTTPS; SaaS zusätzlich öffentlich routbar (SSRF/DNS-Rebinding).
        // On-Premise darf interne Ziele nur nach ausdrücklicher Freigabe
        // (allow_private_targets) erreichen — Konzept §„frei konfigurierbare
        // Server-URL".
        if (! str_starts_with(strtolower($this->server), 'https://')) {
            throw new RuntimeException('Nextcloud server URL must use https.');
        }
        if (! $this->allowPrivateTargets && ! UrlSafety::isPubliclyRoutableHttpUrl($this->server)) {
            throw new RuntimeException('Nextcloud server URL is not a publicly routable https target.');
        }
    }

    public function serverHost(): string {
        return (string) parse_url($this->server, PHP_URL_HOST);
    }

    /** PROPFIND Depth:0 auf den Files-Root: 207 = erreichbar + Auth gültig. */
    public function ping(): bool {
        try {
            $response = $this->send('PROPFIND', $this->filesBaseUrl(), [
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8'],
                'body' => $this->propfindBody('<d:resourcetype/>'),
            ]);
        } catch (Throwable) {
            return false;
        }

        return $response->getStatusCode() === 207;
    }

    /**
     * Direkte Kinder einer Collection (Depth:1). Pfade sind serverrelativ zum
     * Files-Root (ohne führenden Slash). Wirft {@see NextcloudNotFoundException}
     * bei 404 (der Scan entscheidet Stamm- vs. Unterordner).
     *
     * @return list<array{path: string, is_dir: bool, fileid: string, etag: string, mime: ?string, size: int, modified: ?string}>
     */
    public function listChildren(string $serverPath): array {
        $response = $this->send('PROPFIND', $this->fileUrl($serverPath), [
            'headers' => ['Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8'],
            'body' => $this->propfindBody(
                '<oc:fileid/><d:getetag/><d:getcontenttype/><d:getcontentlength/><d:getlastmodified/><d:resourcetype/>'
            ),
        ]);

        $status = $response->getStatusCode();
        if ($status === 404) {
            throw new NextcloudNotFoundException("Nextcloud path not found: {$serverPath}");
        }
        if ($status !== 207) {
            throw new RuntimeException("Nextcloud PROPFIND failed (HTTP {$status}).");
        }

        return $this->parseChildren((string) $response->getBody(), $serverPath);
    }

    /**
     * Verfügbarer/belegter Speicher des Files-Roots (WebDAV-Quota-Properties).
     *
     * @return array{total: int|null, used: int|null}
     */
    public function quota(): array {
        $response = $this->send('PROPFIND', $this->filesBaseUrl(), [
            'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml; charset=utf-8'],
            'body' => $this->propfindBody('<d:quota-available-bytes/><d:quota-used-bytes/>'),
        ]);
        if ($response->getStatusCode() !== 207) {
            throw new RuntimeException('Nextcloud quota PROPFIND failed (HTTP ' . $response->getStatusCode() . ').');
        }

        $nodes = XmlHelper::xpathNodes((string) $response->getBody(), '//d:response', ['d' => self::DAV_NS, 'oc' => self::OC_NS]);
        $self = $nodes[0] ?? null;
        if (! $self instanceof DOMElement) {
            return ['total' => null, 'used' => null];
        }

        $usedRaw = $this->firstValue($self, self::DAV_NS, 'quota-used-bytes');
        $availRaw = $this->firstValue($self, self::DAV_NS, 'quota-available-bytes');
        $used = $usedRaw !== '' ? (int) $usedRaw : null;
        $avail = $availRaw !== '' ? (int) $availRaw : null;

        // quota-available-bytes < 0 ⇒ unbegrenzt/unbekannt (-3/-1) ⇒ kein Total.
        $total = ($used !== null && $avail !== null && $avail >= 0) ? $used + $avail : null;

        return ['total' => $total, 'used' => $used];
    }

    /** Inhalt einer Datei als Stream (Quarantäne-/Restore-Download). */
    public function getStream(string $serverPath): StreamInterface {
        $response = $this->send('GET', $this->fileUrl($serverPath));
        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('Nextcloud GET failed (HTTP ' . $response->getStatusCode() . ').');
        }

        return $response->getBody();
    }

    /** Legt eine Collection (rekursiv) an; idempotent (405 = existiert). */
    public function ensureCollection(string $serverPath): void {
        $accumulated = '';
        foreach (explode('/', trim($serverPath, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }
            $accumulated .= ($accumulated === '' ? '' : '/') . $segment;
            $response = $this->send('MKCOL', $this->fileUrl($accumulated));
            $code = $response->getStatusCode();
            if (! in_array($code, [200, 201, 301, 405], true)) {
                throw new RuntimeException("Nextcloud MKCOL failed for '{$accumulated}' (HTTP {$code}).");
            }
        }
    }

    /**
     * Resumable Upload einer lokalen (bereits verschlüsselten) Datei über
     * Chunked Upload v2; verifiziert die Remote-Größe und liefert sie zurück.
     */
    public function uploadResumable(string $localPath, string $destServerPath): int {
        $size = filesize($localPath);
        $in = @fopen($localPath, 'rb');
        if ($in === false || $size === false) {
            throw new RuntimeException("Backup-Teil nicht lesbar: {$localPath}");
        }

        // MOVE legt keine Ordner an — Zielverzeichnis vorab sicherstellen.
        $parent = trim((string) (str_contains($destServerPath, '/') ? substr($destServerPath, 0, (int) strrpos($destServerPath, '/')) : ''), '/');
        if ($parent !== '') {
            $this->ensureCollection($parent);
        }

        $uploadId = 'wd-' . bin2hex(random_bytes(16));
        $uploadDir = $this->uploadsBaseUrl() . $uploadId;
        $destUrl = $this->fileUrl($destServerPath);
        $totalHeader = ['Destination' => $destUrl, 'OC-Total-Length' => (string) $size];

        try {
            // 1. Upload-Session eröffnen (Ziel als Destination-Header).
            $create = $this->send('MKCOL', $uploadDir, ['headers' => $totalHeader]);
            if (! in_array($create->getStatusCode(), [201, 405], true)) {
                throw new RuntimeException('Nextcloud upload session MKCOL failed (HTTP ' . $create->getStatusCode() . ').');
            }

            // 2. Chunks als 0-basierten Start-Offset (lexikografisch sortierbar).
            $offset = 0;
            while (! feof($in)) {
                $chunk = fread($in, max(1, $this->chunkSize));
                if ($chunk === false) {
                    throw new RuntimeException("Lesefehler in {$localPath}.");
                }
                if ($chunk === '') {
                    continue;
                }
                $chunkName = str_pad((string) $offset, 16, '0', STR_PAD_LEFT);
                $put = $this->send('PUT', $uploadDir . '/' . $chunkName, [
                    'headers' => ['OC-Total-Length' => (string) $size, 'Content-Type' => 'application/octet-stream'],
                    'body' => $chunk,
                ]);
                if ($put->getStatusCode() < 200 || $put->getStatusCode() >= 300) {
                    throw new RuntimeException('Nextcloud chunk PUT failed (HTTP ' . $put->getStatusCode() . ').');
                }
                $offset += strlen($chunk);
            }

            // 3. Zusammensetzen: MOVE der Sonderdatei `.file` auf das Ziel.
            $move = $this->send('MOVE', $uploadDir . '/.file', ['headers' => $totalHeader]);
            if (! in_array($move->getStatusCode(), [201, 204], true)) {
                throw new RuntimeException('Nextcloud upload assemble MOVE failed (HTTP ' . $move->getStatusCode() . ').');
            }
        } finally {
            fclose($in);
        }

        // 4. Remote-Größe verifizieren (Konzept §„über die Größe verifiziert").
        $remoteSize = $this->contentLength($destServerPath);
        if ($remoteSize !== (int) $size) {
            throw new RuntimeException("Nextcloud-Upload unvollständig: remote {$remoteSize} B, lokal {$size} B.");
        }

        return (int) $size;
    }

    /** Löscht ein Objekt; 404 gilt als bereits gelöscht (idempotent). */
    public function deletePath(string $serverPath): bool {
        $response = $this->send('DELETE', $this->fileUrl($serverPath));
        $code = $response->getStatusCode();
        if ($code === 404) {
            return true;
        }

        return $code >= 200 && $code < 300;
    }

    // ── URL-/Pfad-Bausteine ─────────────────────────────────────────────

    /** WebDAV-Files-Root der Anbindung (`.../remote.php/dav/files/<user>/`). */
    public function filesBaseUrl(): string {
        return $this->server . '/remote.php/dav/files/' . rawurlencode($this->username) . '/';
    }

    private function uploadsBaseUrl(): string {
        return $this->server . '/remote.php/dav/uploads/' . rawurlencode($this->username) . '/';
    }

    /** Dekodierter Pfadpräfix zum Strippen aus PROPFIND-hrefs. */
    private function filesRootPath(): string {
        return '/remote.php/dav/files/' . $this->username . '/';
    }

    private function fileUrl(string $serverPath): string {
        return $this->filesBaseUrl() . $this->encodePath($serverPath);
    }

    private function encodePath(string $serverPath): string {
        $segments = array_map(
            'rawurlencode',
            array_values(array_filter(explode('/', trim($serverPath, '/')), static fn (string $s): bool => $s !== '')),
        );

        return implode('/', $segments);
    }

    private function contentLength(string $serverPath): int {
        $response = $this->send('HEAD', $this->fileUrl($serverPath));
        if ($response->getStatusCode() >= 400) {
            return -1;
        }

        return (int) $response->getHeaderLine('Content-Length');
    }

    private function propfindBody(string $props): string {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop>'
            . $props
            . '</d:prop></d:propfind>';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function send(string $method, string $url, array $options = []): \Psr\Http\Message\ResponseInterface {
        $options['auth'] = [$this->username, $this->appPassword];
        $options['http_errors'] = false;

        try {
            return $this->http->request($method, $url, $options);
        } catch (Throwable $e) {
            throw new RuntimeException('Nextcloud WebDAV request failed (' . class_basename($e) . ').', 0, $e);
        }
    }

    /**
     * @return list<array{path: string, is_dir: bool, fileid: string, etag: string, mime: ?string, size: int, modified: ?string}>
     */
    private function parseChildren(string $xml, string $requestedServerPath): array {
        $responses = XmlHelper::xpathNodes($xml, '//d:response', ['d' => self::DAV_NS, 'oc' => self::OC_NS]);
        $requestedNorm = trim($requestedServerPath, '/');

        $out = [];
        foreach ($responses as $response) {
            $hrefNode = $response->getElementsByTagNameNS(self::DAV_NS, 'href')->item(0);
            if ($hrefNode === null) {
                continue;
            }
            $path = $this->hrefToServerPath((string) $hrefNode->nodeValue);
            if ($path === null || trim($path, '/') === $requestedNorm) {
                continue; // Selbst-Eintrag der Collection überspringen.
            }

            $out[] = [
                'path' => trim($path, '/'),
                'is_dir' => $response->getElementsByTagNameNS(self::DAV_NS, 'collection')->length > 0,
                'fileid' => $this->firstValue($response, self::OC_NS, 'fileid'),
                'etag' => trim($this->firstValue($response, self::DAV_NS, 'getetag'), '"'),
                'mime' => ($m = $this->firstValue($response, self::DAV_NS, 'getcontenttype')) !== '' ? $m : null,
                'size' => (int) $this->firstValue($response, self::DAV_NS, 'getcontentlength'),
                'modified' => ($mod = $this->firstValue($response, self::DAV_NS, 'getlastmodified')) !== '' ? $mod : null,
            ];
        }

        return $out;
    }

    private function hrefToServerPath(string $href): ?string {
        $href = trim($href);
        if ($href === '') {
            return null;
        }
        if (str_contains($href, '://')) {
            $href = (string) parse_url($href, PHP_URL_PATH);
        }
        $decoded = rawurldecode($href);
        $root = $this->filesRootPath();
        if (str_starts_with($decoded, $root)) {
            return substr($decoded, strlen($root));
        }
        // Die Collection selbst wird häufig ohne abschließenden Slash geliefert.
        if (rtrim($decoded, '/') === rtrim($root, '/')) {
            return '';
        }

        return null;
    }

    /** Erster nicht-leerer Wert einer Property (leere 404-propstat ignorieren). */
    private function firstValue(DOMElement $response, string $ns, string $local): string {
        foreach ($response->getElementsByTagNameNS($ns, $local) as $node) {
            $value = trim((string) $node->nodeValue);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
