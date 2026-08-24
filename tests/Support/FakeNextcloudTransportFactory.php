<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeNextcloudTransportFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use App\Plugins\Nextcloud\Api\NextcloudWebdavClient;
use App\Plugins\Nextcloud\Contracts\NextcloudTransportFactory;
use App\Plugins\Support\PluginApiClient;
use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use SensitiveParameter;

/**
 * Test-Factory: liefert einen {@see NextcloudWebdavClient} über einen
 * PluginApiClient mit Guzzle-MockHandler-Transport (gleiche Naht wie
 * produktiv, C4-Rest; kein echter HTTP-Verkehr, kein DNS/SSRF —
 * `allowPrivateTargets` ist im Test aktiv). Die Antwortschlange und die
 * aufgezeichnete Request-Historie sind für Assertions öffentlich.
 */
class FakeNextcloudTransportFactory implements NextcloudTransportFactory {
    /** @var list<array{request: \Psr\Http\Message\RequestInterface, response: \Psr\Http\Message\ResponseInterface, error: mixed, options: array<mixed>}> */
    public array $history = [];

    private ?NextcloudWebdavClient $client = null;

    /** @param list<Response> $responses */
    public function __construct(private readonly array $responses, private readonly int $chunkSize = 1024) {}

    public function forCredentials(string $serverUrl, string $username, #[SensitiveParameter] string $appPassword): NextcloudWebdavClient {
        // Einen einzigen Client (eine MockHandler-Queue) über alle Aufrufe
        // hinweg wiederverwenden — sonst würde jeder Runner-Seitenaufruf die
        // Antwortschlange von vorn abspielen.
        if ($this->client === null) {
            $mock = new MockHandler($this->responses);
            $stack = HandlerStack::create($mock);
            $stack->push(Middleware::history($this->history));

            $this->client = new NextcloudWebdavClient(
                new PluginApiClient('nextcloud', $serverUrl, new Client(['handler' => $stack])),
                $serverUrl,
                $username,
                $appPassword,
                allowPrivateTargets: true,
                chunkSize: $this->chunkSize,
            );
        }

        return $this->client;
    }

    /** @param array<string, string> $headers */
    public static function response(int $status = 200, string $body = '', array $headers = []): Response {
        return new Response($status, $headers, $body);
    }

    public static function multistatus(string $xml): Response {
        return new Response(207, ['Content-Type' => 'application/xml; charset=utf-8'], $xml);
    }

    /**
     * PROPFIND-Multistatus für eine Collection: erster Eintrag ist die
     * Collection selbst, danach je Kind ein `$children`-Datensatz.
     *
     * @param  list<array{path: string, dir?: bool, fileid?: string, etag?: string, mime?: string, size?: int}>  $children
     */
    public static function folder(string $selfHref, array $children): Response {
        $xml = '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">';
        $xml .= '<d:response><d:href>' . $selfHref . '</d:href>'
            . '<d:propstat><d:prop><d:resourcetype><d:collection/></d:resourcetype></d:prop>'
            . '<d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>';

        foreach ($children as $child) {
            $isDir = $child['dir'] ?? false;
            $resourcetype = $isDir ? '<d:resourcetype><d:collection/></d:resourcetype>' : '<d:resourcetype/>';
            $xml .= '<d:response><d:href>' . $child['path'] . '</d:href><d:propstat><d:prop>'
                . '<oc:fileid>' . ($child['fileid'] ?? '') . '</oc:fileid>'
                . '<d:getetag>&quot;' . ($child['etag'] ?? '') . '&quot;</d:getetag>'
                . '<d:getcontenttype>' . ($child['mime'] ?? '') . '</d:getcontenttype>'
                . '<d:getcontentlength>' . ($child['size'] ?? 0) . '</d:getcontentlength>'
                . '<d:getlastmodified>Mon, 14 Jul 2026 10:00:00 GMT</d:getlastmodified>'
                . $resourcetype
                . '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>';
        }

        return self::multistatus($xml . '</d:multistatus>');
    }
}
