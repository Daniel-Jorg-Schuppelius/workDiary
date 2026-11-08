<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportRustDeskTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Plugins\RemoteSupport\Api\{RustDeskApiServerClient, RustDeskProClient};
use App\Plugins\RemoteSupport\Exceptions\RemoteProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** RustDesk Server Pro (MVP-1473) und Open-Source-API-Server (MVP-1474). */
class RemoteSupportRustDeskTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string PRO = 'https://rustdesk.example.com';

    private const string OSS = 'https://rustdesk-api.example.com';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function window(): array {
        return [CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'), CarbonImmutable::parse('2026-11-03 00:00:00', 'UTC')];
    }

    public function test_pro_reads_audits_with_bearer_token_and_maps_closed_connections(): void {
        [$from, $to] = $this->window();
        $start = CarbonImmutable::parse('2026-11-02 10:00:00', 'UTC');
        $queries = [];

        FakePluginHttp::fake([
            self::PRO . '/api/audits/conn*' => function (RequestInterface $request) use (&$queries, $start) {
                parse_str($request->getUri()->getQuery(), $query);
                $queries[] = $query;
                $this->assertSame('Bearer rd-token', $request->getHeaderLine('Authorization'));

                return FakePluginHttp::response([
                    'total' => 4,
                    'data' => [
                        ['guid' => 'g-1', 'remote' => '123 456 789', 'remote_device_name' => 'PC-BUCHHALTUNG', 'conn_type' => 0, 'created_at' => $start->getTimestamp(), 'end_time' => $start->addMinutes(45)->getTimestamp(), 'note' => 'Drucker eingerichtet', 'controller_user' => 'tech'],
                        ['guid' => 'g-open', 'remote' => '123456789', 'conn_type' => 0, 'created_at' => $start->addHour()->getTimestamp(), 'end_time' => null],
                        ['guid' => 'g-old', 'remote' => '123456789', 'conn_type' => 0, 'created_at' => $start->subDays(20)->getTimestamp(), 'end_time' => $start->subDays(20)->addMinutes(5)->getTimestamp()],
                        ['guid' => 'g-file', 'remote' => '987654321', 'conn_type' => 1, 'created_at' => $start->addHours(2)->getTimestamp(), 'end_time' => $start->addHours(2)->addMinutes(3)->getTimestamp()],
                    ],
                ]);
            },
        ]);

        $sessions = (new RustDeskProClient(self::PRO, 'rd-token'))->fetchSessions($from, $to);

        $this->assertCount(2, $sessions);
        $this->assertSame('rustdesk', $sessions[0]->provider);
        $this->assertSame('g-1', $sessions[0]->sessionId);
        $this->assertSame('123456789', $sessions[0]->remoteId, 'Leerzeichen der Anzeige-ID entfernt');
        $this->assertSame('PC-BUCHHALTUNG', $sessions[0]->alias);
        $this->assertSame(45, $sessions[0]->minutes());
        $this->assertSame('Drucker eingerichtet · tech', $sessions[0]->note);
        $this->assertSame('987654321', $sessions[1]->remoteId);
        $this->assertStringContainsString((string) __('Dateiübertragung'), (string) $sessions[1]->note);

        $this->assertCount(1, $queries);
        $this->assertSame('1', $queries[0]['current']);
        $this->assertSame('100', $queries[0]['pageSize']);
        $this->assertSame('2026-11-01 00:00:00', $queries[0]['created_at']);
    }

    public function test_pro_pages_until_total_is_read(): void {
        [$from, $to] = $this->window();
        $start = CarbonImmutable::parse('2026-11-02 08:00:00', 'UTC');
        $page = static function (int $offset, int $count) use ($start): array {
            $rows = [];
            for ($i = 0; $i < $count; $i++) {
                $n = $offset + $i;
                $rows[] = ['guid' => 'g-' . $n, 'remote' => (string) (100000000 + $n), 'conn_type' => 0, 'created_at' => $start->addMinutes($n)->getTimestamp(), 'end_time' => $start->addMinutes($n + 1)->getTimestamp()];
            }

            return $rows;
        };
        $calls = 0;
        FakePluginHttp::fake([
            self::PRO . '/api/audits/conn*' => function (RequestInterface $request) use (&$calls, $page) {
                parse_str($request->getUri()->getQuery(), $query);
                $calls++;

                return FakePluginHttp::response(['total' => 130, 'data' => (int) $query['current'] === 1 ? $page(0, 100) : $page(100, 30)]);
            },
        ]);

        $sessions = (new RustDeskProClient(self::PRO, 'rd-token'))->fetchSessions($from, $to);

        $this->assertCount(130, $sessions);
        $this->assertSame(2, $calls);
    }

    public function test_pro_rejected_request_throws(): void {
        [$from, $to] = $this->window();
        FakePluginHttp::fake([self::PRO . '/api/audits/conn*' => FakePluginHttp::response(['error' => 'unauthorized'], 401)]);

        $this->expectException(RemoteProviderException::class);
        $this->expectExceptionMessage('HTTP 401');
        (new RustDeskProClient(self::PRO, 'wrong'))->fetchSessions($from, $to);
    }

    public function test_api_server_logs_in_once_and_reads_until_window_start(): void {
        [$from, $to] = $this->window();
        $closeA = CarbonImmutable::parse('2026-11-02 10:30:00', 'UTC');
        $logins = 0;
        $listHeaders = [];

        FakePluginHttp::fake([
            self::OSS . '/api/admin/login' => function (RequestInterface $request) use (&$logins) {
                $logins++;
                $body = json_decode((string) $request->getBody(), true);
                $this->assertSame(['username' => 'admin', 'password' => 'secret', 'platform' => 'workDiary'], $body);

                return FakePluginHttp::response(['code' => 0, 'message' => 'success', 'data' => ['token' => 'T-1', 'username' => 'admin']]);
            },
            self::OSS . '/api/admin/audit_conn/list*' => function (RequestInterface $request) use (&$listHeaders, $closeA) {
                $listHeaders[] = $request->getHeaderLine('api-token');
                parse_str($request->getUri()->getQuery(), $query);
                $this->assertSame('100', $query['page_size']);

                return FakePluginHttp::response(['code' => 0, 'message' => 'success', 'data' => [
                    'page' => 1, 'page_size' => 100, 'total' => 3,
                    'list' => [
                        // Serverzeit Europe/Berlin (CET): 11:00 → 10:00 UTC, geschlossen 10:30 UTC.
                        ['id' => 31, 'action' => 'new', 'conn_id' => 7, 'peer_id' => '123456789', 'from_peer' => '555000111', 'from_name' => 'TECH-NB', 'ip' => '203.0.113.5', 'type' => 0, 'created_at' => '2026-11-02 11:00:00', 'close_time' => $closeA->getTimestamp()],
                        ['id' => 30, 'action' => 'new', 'conn_id' => 6, 'peer_id' => '123456789', 'from_peer' => '555000111', 'from_name' => 'TECH-NB', 'type' => 0, 'created_at' => '2026-11-02 09:00:00', 'close_time' => 0],
                        ['id' => 29, 'action' => 'new', 'conn_id' => 5, 'peer_id' => '123456789', 'type' => 0, 'created_at' => '2026-10-15 09:00:00', 'close_time' => 1760000000],
                    ],
                ]]);
            },
        ]);

        $sessions = (new RustDeskApiServerClient(self::OSS, 'admin', 'secret', 'Europe/Berlin'))->fetchSessions($from, $to);

        $this->assertSame(1, $logins);
        $this->assertSame(['T-1'], $listHeaders);
        $this->assertCount(1, $sessions, 'offene Verbindung und Verbindung vor dem Fenster fallen weg');
        $this->assertSame('31', $sessions[0]->sessionId);
        $this->assertSame('123456789', $sessions[0]->remoteId);
        $this->assertSame('2026-11-02 10:00:00', $sessions[0]->startedAt->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(30, $sessions[0]->minutes());
        $this->assertNull($sessions[0]->alias, 'der API-Server kennt keinen Gerätenamen');
        $this->assertSame((string) __('von :name', ['name' => 'TECH-NB']), $sessions[0]->note);
    }

    public function test_api_server_rejected_login_throws(): void {
        [$from, $to] = $this->window();
        FakePluginHttp::fake([
            self::OSS . '/api/admin/login' => FakePluginHttp::response(['code' => 101, 'message' => 'UsernameOrPasswordError', 'data' => null]),
        ]);

        $this->expectException(RemoteProviderException::class);
        $this->expectExceptionMessage('UsernameOrPasswordError');
        (new RustDeskApiServerClient(self::OSS, 'admin', 'wrong'))->fetchSessions($from, $to);
    }

    public function test_api_server_is_not_configured_without_credentials(): void {
        $this->assertFalse((new RustDeskApiServerClient(self::OSS, 'admin', null))->isConfigured());
        $this->assertFalse((new RustDeskProClient(null, 'token'))->isConfigured());
        $this->assertTrue((new RustDeskProClient(self::PRO, 'token'))->isConfigured());
    }
}
