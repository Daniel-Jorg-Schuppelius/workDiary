<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportPcvisitTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Plugins\RemoteSupport\Api\PcvisitClient;
use App\Plugins\RemoteSupport\Exceptions\RemoteProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** pcvisit GraphQL (MVP-1476). */
class RemoteSupportPcvisitTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string ENDPOINT = 'https://app.pcvisit.de/graphql';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private static function jwt(int $exp): string {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        return $b64('{"alg":"RS256","typ":"JWT"}') . '.' . $b64('{"sub":"user","exp":' . $exp . '}') . '.sig';
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function fakeApi(array $nodes, int &$jwtCalls, int &$historyCalls, ?callable $historyOverride = null): string {
        $token = self::jwt(time() + 3600);
        FakePluginHttp::fake([
            self::ENDPOINT => function (RequestInterface $request) use (&$jwtCalls, &$historyCalls, $nodes, $token, $historyOverride) {
                $body = json_decode((string) $request->getBody(), true);
                if (str_contains((string) $body['query'], 'SiRetrieveSignedJwt')) {
                    $jwtCalls++;
                    $this->assertSame('INT-TOKEN', $request->getHeaderLine('x-api-key'));
                    $this->assertSame(['userApiToken' => 'USER-TOKEN', 'integratorApiToken' => 'INT-TOKEN'], $body['variables']);

                    return FakePluginHttp::response(['data' => ['SiRetrieveSignedJwt' => ['__typename' => 'SignedJwtWasRetrieved', 'token' => $token, 'customerUuid' => 'cust-1']]]);
                }
                $historyCalls++;
                $this->assertSame('Bearer ' . $token, $request->getHeaderLine('Authorization'));
                $this->assertSame('cust-1', $body['variables']['company']);
                if ($historyOverride !== null) {
                    $override = $historyOverride($historyCalls, $body);
                    if ($override !== null) {
                        return $override;
                    }
                }

                return FakePluginHttp::response(['data' => ['AcQuerySessionsHistory' => ['__typename' => 'SessionProtocolEdge', 'totalCount' => count($nodes), 'batchLoadingIsStillActive' => false, 'nodes' => $nodes]]]);
            },
        ]);

        return $token;
    }

    public function test_reads_closed_sessions_with_cached_jwt(): void {
        $from = CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC');
        $to = CarbonImmutable::parse('2026-11-03 00:00:00', 'UTC');
        $start = CarbonImmutable::parse('2026-11-02 09:00:00', 'UTC');
        $jwtCalls = 0;
        $historyCalls = 0;
        $this->fakeApi([
            ['cursor' => 'c1', 'value' => [
                'id' => 'task-1', 'name' => 'Server-Update', 'sessionCreatedAt' => $start->getTimestampMs(), 'sessionFinishedAt' => $start->addMinutes(50)->getTimestampMs(),
                'state' => 'SessionClosed', 'type' => 'DeviceSessionForSupporter', 'duration' => 3000000, 'customDuration' => 2700000, 'invoiceState' => 'Pending',
                'participants' => [['isSessionCreator' => true, 'supporterContact' => ['email' => 'tech@example.test']]],
                'target' => ['computerId' => 'asset-uuid-1', 'computerDisplayName' => 'SRV-01', 'computerHostName' => 'srv01'],
                'clientCompany' => ['clientId' => 'K-7', 'clientName' => 'Musterkunde GmbH'],
            ]],
            ['cursor' => 'c2', 'value' => [
                'id' => 'task-open', 'name' => 'läuft noch', 'sessionCreatedAt' => $start->getTimestampMs(), 'sessionFinishedAt' => null, 'state' => 'ServingStream', 'type' => 'AdHocSessionForSupporter',
                'participants' => [], 'target' => ['computerId' => 'asset-uuid-1', 'computerDisplayName' => 'SRV-01'], 'clientCompany' => null,
            ]],
        ], $jwtCalls, $historyCalls);

        $client = new PcvisitClient('INT-TOKEN', 'USER-TOKEN', self::ENDPOINT, $this->organization->id);
        $sessions = $client->fetchSessions($from, $to);
        $again = $client->fetchSessions($from, $to);

        $this->assertCount(1, $sessions, 'nur abgeschlossene Sitzungen');
        $this->assertCount(1, $again);
        $this->assertSame(1, $jwtCalls, 'das JWT kommt beim zweiten Lauf aus dem Cache');
        $this->assertSame(2, $historyCalls);
        $this->assertSame('pcvisit', $sessions[0]->provider);
        $this->assertSame('task-1', $sessions[0]->sessionId);
        $this->assertSame('asset-uuid-1', $sessions[0]->remoteId);
        $this->assertSame('SRV-01', $sessions[0]->alias);
        $this->assertSame(45, $sessions[0]->minutes(), 'customDuration (45 min) vor duration (50 min)');
        $this->assertSame('Server-Update · Musterkunde GmbH · tech@example.test', $sessions[0]->note);
    }

    public function test_failure_result_throws_with_error_and_comment(): void {
        $jwtCalls = 0;
        $historyCalls = 0;
        $this->fakeApi([], $jwtCalls, $historyCalls, static fn (): \Psr\Http\Message\ResponseInterface => FakePluginHttp::response(['data' => ['AcQuerySessionsHistory' => [
            '__typename' => 'Failure', 'error' => 'TryAgainLater', 'comment_for_dev' => 'Retry-After: 5 seconds (until Mon, 01 Jan 2024 12:00:00 GMT)', 'comment_for_user' => 'Bitte später erneut versuchen.',
        ]]]));

        $this->expectException(RemoteProviderException::class);
        $this->expectExceptionMessage('TryAgainLater');
        (new PcvisitClient('INT-TOKEN', 'USER-TOKEN', self::ENDPOINT, $this->organization->id))->fetchSessions(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-03'));
    }

    public function test_expired_token_is_renewed_once(): void {
        $jwtCalls = 0;
        $historyCalls = 0;
        $this->fakeApi([], $jwtCalls, $historyCalls, static fn (int $call): ?\Psr\Http\Message\ResponseInterface => $call === 1
            ? FakePluginHttp::response(['data' => ['AcQuerySessionsHistory' => ['__typename' => 'Failure', 'error' => 'TokenExpired', 'comment_for_dev' => 'expired']]])
            : null);

        $sessions = (new PcvisitClient('INT-TOKEN', 'USER-TOKEN', self::ENDPOINT, $this->organization->id))->fetchSessions(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-03'));

        $this->assertSame([], $sessions);
        $this->assertSame(2, $jwtCalls);
        $this->assertSame(2, $historyCalls);
    }

    public function test_jwt_expiry_is_read_from_payload(): void {
        $this->assertSame(1800000000, PcvisitClient::jwtExpiry(self::jwt(1800000000)));
        $this->assertNull(PcvisitClient::jwtExpiry('kein-jwt'));
        $this->assertFalse((new PcvisitClient('INT', null))->isConfigured());
    }
}
