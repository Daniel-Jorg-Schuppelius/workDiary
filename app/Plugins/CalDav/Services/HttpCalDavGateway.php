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
use CommonToolkit\Helper\Data\XmlHelper;
use DateTimeInterface;
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

    public function syncEvents(string $prevSyncToken, array $localEtags, DateTimeInterface $windowStart, DateTimeInterface $windowEnd): CalDavSyncPage {
        $collection = rtrim($this->connection->base_url, '/') . '/' . trim($this->connection->calendar_path, '/') . '/';

        $page = $this->syncCollection($collection, $prevSyncToken);
        if ($page !== null) {
            return $page;
        }

        // Server ohne sync-collection: Zeitfenster + ETag-Vergleich.
        return $this->timeRangeSync($collection, $localEtags, $windowStart, $windowEnd);
    }

    /** RFC-6578-Report; null = Server kann ihn nicht (kein Fehlerfall). */
    private function syncCollection(string $collection, string $prevSyncToken): ?CalDavSyncPage {
        $body = sprintf(
            '<?xml version="1.0" encoding="utf-8"?>'
                . '<d:sync-collection xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'
                . '<d:sync-token>%s</d:sync-token><d:sync-level>1</d:sync-level>'
                . '<d:prop><d:getetag/><c:calendar-data/></d:prop></d:sync-collection>',
            htmlspecialchars($prevSyncToken, ENT_XML1),
        );

        try {
            $response = $this->http->request('REPORT', $collection, [
                'auth' => [$this->connection->username, $this->connection->app_password],
                'headers' => ['Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8'],
                'body' => $body,
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            return null;
        }

        if ($response->getStatusCode() !== 207) {
            return null; // 400/403/501 & Co. ⇒ Fallback, nicht Abbruch
        }

        $xml = (string) $response->getBody();
        [$changed, $deleted] = $this->parseMultiStatus($xml);
        $token = (string) (XmlHelper::xpathFirst($xml, '//d:multistatus/d:sync-token', ['d' => 'DAV:']) ?? '');

        return new CalDavSyncPage($changed, $deleted, $token);
    }

    /**
     * `calendar-query` über ein Zeitfenster: liefert href + ETag; geholt wird
     * nur, was sich gegenüber dem lokalen ETag geändert hat. Gelöschtes
     * erkennt man hier nur am Fehlen — `deleted` trägt deshalb die
     * Objektnamen, die im Fenster nicht mehr auftauchen.
     *
     * @param  array<string, string>  $localEtags  Objektname → ETag
     */
    private function timeRangeSync(string $collection, array $localEtags, DateTimeInterface $windowStart, DateTimeInterface $windowEnd): CalDavSyncPage {
        $body = sprintf(
            '<?xml version="1.0" encoding="utf-8"?>'
                . '<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'
                . '<d:prop><d:getetag/></d:prop>'
                . '<c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT">'
                . '<c:time-range start="%s" end="%s"/>'
                . '</c:comp-filter></c:comp-filter></c:filter></c:calendar-query>',
            $windowStart->format('Ymd\THis\Z'),
            $windowEnd->format('Ymd\THis\Z'),
        );

        try {
            $response = $this->http->request('REPORT', $collection, [
                'auth' => [$this->connection->username, $this->connection->app_password],
                'headers' => ['Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8'],
                'body' => $body,
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            return new CalDavSyncPage([], [], '');
        }

        if ($response->getStatusCode() !== 207) {
            return new CalDavSyncPage([], [], '');
        }

        $changed = [];
        $seen = [];
        foreach ($this->responseRows((string) $response->getBody()) as $row) {
            // Verglichen wird über den Objektnamen: der href-Präfix ist
            // serverabhängig, der letzte Pfadteil ist unsere Remote-ID.
            $key = rawurldecode(basename($row['href']));
            $seen[] = $key;
            if ($row['etag'] !== '' && ($localEtags[$key] ?? null) === $row['etag']) {
                continue; // unverändert
            }
            $ics = $row['ics'] !== '' ? $row['ics'] : $this->fetchObject($row['href']);
            if ($ics !== '') {
                $changed[] = new CalDavEventChange($row['href'], $row['etag'], $ics);
            }
        }

        // Ohne sync-collection erkennt man Löschungen nur am Fehlen.
        $deleted = array_values(array_diff(array_keys($localEtags), $seen));

        return new CalDavSyncPage($changed, $deleted, '');
    }

    /** Einzelobjekt nachladen, wenn der Report kein calendar-data mitliefert. */
    private function fetchObject(string $href): string {
        try {
            $response = $this->http->request('GET', $this->absolute($href), [
                'auth' => [$this->connection->username, $this->connection->app_password],
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            return '';
        }

        return $this->isSuccess($response->getStatusCode()) ? (string) $response->getBody() : '';
    }

    /**
     * Multi-Status auswerten: 404/410-Antworten sind Löschungen, alles andere
     * mit href gilt als geändert.
     *
     * @return array{0: list<CalDavEventChange>, 1: list<string>}
     */
    private function parseMultiStatus(string $xml): array {
        $changed = [];
        $deleted = [];
        foreach ($this->responseRows($xml) as $row) {
            if ($row['gone']) {
                $deleted[] = $row['href'];

                continue;
            }
            $ics = $row['ics'] !== '' ? $row['ics'] : $this->fetchObject($row['href']);
            if ($ics !== '') {
                $changed[] = new CalDavEventChange($row['href'], $row['etag'], $ics);
            }
        }

        return [$changed, $deleted];
    }

    /**
     * Zeilen eines Multi-Status. Der Parser kommt aus dem common-toolkit —
     * DAV-XML ist gewöhnliches XML mit Namensräumen.
     *
     * @return list<array{href: string, etag: string, ics: string, gone: bool}>
     */
    private function responseRows(string $xml): array {
        $ns = ['d' => 'DAV:', 'c' => 'urn:ietf:params:xml:ns:caldav'];
        $rows = [];

        foreach (XmlHelper::xpathNodes($xml, '//d:response', $ns) as $node) {
            $href = trim((string) ($this->firstValue($node, 'd:href', $ns) ?? ''));
            if ($href === '') {
                continue;
            }
            $status = (string) ($this->firstValue($node, 'd:status', $ns) ?? '');
            $rows[] = [
                'href' => $href,
                'etag' => trim((string) ($this->firstValue($node, './/d:getetag', $ns) ?? ''), " \t\n\r\0\x0B\""),
                'ics' => (string) ($this->firstValue($node, './/c:calendar-data', $ns) ?? ''),
                'gone' => str_contains($status, ' 404') || str_contains($status, ' 410'),
            ];
        }

        return $rows;
    }

    /** @param array<string, string> $ns */
    private function firstValue(\DOMElement $node, string $path, array $ns): ?string {
        $document = $node->ownerDocument;
        if ($document === null) {
            return null;
        }
        $xpath = new \DOMXPath($document);
        foreach ($ns as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }
        $found = $xpath->query($path, $node);

        return ($found !== false && $found->length > 0) ? (string) $found->item(0)?->nodeValue : null;
    }

    /** Relative hrefs des Servers auf die Basis-URL beziehen. */
    private function absolute(string $href): string {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        $base = (string) $this->connection->base_url;
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $origin . '/' . ltrim($href, '/');
    }

    private function isSuccess(int $status): bool {
        return $status >= 200 && $status < 300;
    }
}
