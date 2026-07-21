<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AnyDeskClientTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Plugins\RemoteSupport\Providers\AnyDeskClient;
use Carbon\CarbonImmutable;
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * AnyDesk-REST-API v1: Signatur-Format (`AD LICENSE:TIMESTAMP:TOKEN`, HMAC über
 * Pfad+Query) und Response-Mapping (`list`, `start-time`/`end-time`) gegen die
 * Referenz-Implementierung github.com/anydesk/rest-api.
 */
class AnyDeskClientTest extends TestCase {
    private const LICENSE = 'BM5FFAH4A37D59N';

    private const API_PASSWORD = 'geheimes-api-passwort';

    /** Prüft Header-Format und rechnet die HMAC mit dem Referenz-Algorithmus nach. */
    private function assertValidSignature(RequestInterface $request, string $method): bool {
        $auth = $request->getHeaderLine('Authorization');
        if (! preg_match('/^AD ' . self::LICENSE . ':(\d+):([A-Za-z0-9+\/]+=*)$/', $auth, $m)) {
            return false;
        }

        $resource = $request->getUri()->getPath();
        if ($request->getUri()->getQuery() !== '') {
            $resource .= '?' . $request->getUri()->getQuery();
        }

        $requestString = implode("\n", [$method, $resource, $m[1], base64_encode(hash('sha1', '', true))]);
        $expected = base64_encode(hash_hmac('sha1', $requestString, self::API_PASSWORD, true));

        return $m[2] === $expected;
    }

    public function test_fetch_sessions_signs_path_with_query_and_maps_official_payload(): void {
        $fake = FakePluginHttp::fake([
            'https://v1.api.anydesk.com:8081/sessions*' => FakePluginHttp::response([
                'list' => [
                    [
                        'sid' => 900537412,
                        'from' => ['cid' => 100000001, 'alias' => 'support@ad'],
                        'to' => ['cid' => 362798056, 'alias' => 'buero-pc@ad'],
                        'active' => false,
                        'start-time' => 1752998529,
                        'end-time' => 1753002188,
                        'duration' => 3659,
                        'comment' => 'Druckertreiber',
                    ],
                    // Ohne Endzeit (laufende Sitzung) → wird übersprungen.
                    [
                        'sid' => 900537413,
                        'to' => ['cid' => 362798056, 'alias' => 'buero-pc@ad'],
                        'active' => true,
                        'start-time' => 1753002200,
                        'end-time' => null,
                    ],
                ],
            ]),
        ]);

        $client = new AnyDeskClient(self::LICENSE, self::API_PASSWORD);
        $sessions = $client->fetchSessions(
            CarbonImmutable::createFromTimestampUTC(1752000000),
            CarbonImmutable::createFromTimestampUTC(1753000000),
        );

        $this->assertCount(1, $sessions);
        $this->assertSame('anydesk', $sessions[0]->provider);
        $this->assertSame('900537412', $sessions[0]->sessionId);
        $this->assertSame('362798056', $sessions[0]->remoteId);
        $this->assertSame(1752998529, $sessions[0]->startedAt->getTimestamp());
        $this->assertSame(1753002188, $sessions[0]->endedAt->getTimestamp());
        $this->assertSame('Druckertreiber', $sessions[0]->note);
        $this->assertSame('buero-pc@ad', $sessions[0]->alias);

        $fake->assertSent(function (RequestInterface $r): bool {
            return str_contains($r->getUri()->getQuery(), 'from=1752000000')
                && str_contains($r->getUri()->getQuery(), 'to=1753000000')
                && $this->assertValidSignature($r, 'GET');
        });
    }

    public function test_ping_signs_auth_resource(): void {
        $fake = FakePluginHttp::fake();

        $client = new AnyDeskClient(self::LICENSE, self::API_PASSWORD);
        $this->assertTrue($client->ping());

        $fake->assertSent(function (RequestInterface $r): bool {
            return $r->getUri()->getPath() === '/auth'
                && $this->assertValidSignature($r, 'GET');
        });
    }
}
