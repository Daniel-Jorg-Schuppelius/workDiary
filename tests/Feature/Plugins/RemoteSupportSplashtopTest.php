<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportSplashtopTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Plugins\RemoteSupport\Api\SplashtopClient;
use App\Plugins\RemoteSupport\Exceptions\RemoteProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Splashtop Open API (MVP-1475). */
class RemoteSupportSplashtopTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const EU = 'https://webapi.splashtop.eu';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_reads_closed_sessions_per_kind_with_team_from_basic_info(): void {
        $from = CarbonImmutable::now()->subDays(2)->startOfHour();
        $to = CarbonImmutable::now()->startOfHour();
        $start = $from->addHours(5);
        $bodies = [];

        FakePluginHttp::fake([
            self::EU . '/api/open/v1/users/basic_info' => function (RequestInterface $request) {
                $this->assertSame('Bearer sp-token', $request->getHeaderLine('Authorization'));

                return FakePluginHttp::response(['result' => 20200, 'commands' => [], 'messages' => [], 'data' => [
                    'name' => 'owner@example.test',
                    'scopes' => ['logs' => ['stb_team_id' => 11125], 'team_computers' => ['stb_team_id' => 11125]],
                ]]);
            },
            self::EU . '/api/open/v1/teams/logs/closed_sessions' => function (RequestInterface $request) use (&$bodies, $start) {
                $body = json_decode((string) $request->getBody(), true);
                $bodies[] = $body;
                $items = $body['service_kinds'] === 'unattended_remote' ? [
                    ['id' => 333938, 'srs_uuid' => '4a938402feabe3add440fc7cbf3eed14', 'srs_name' => 'PC-EMPFANG', 'service_kind' => 'unattended_remote', 'created_at' => $start->toIso8601ZuluString(), 'duration' => 1800, 'note' => 'kurz', 'full_note' => 'Scanner eingerichtet', 'src_user_email' => 'tech@example.test', 'status' => 'closed'],
                    ['id' => 333939, 'srs_uuid' => '', 'created_at' => $start->toIso8601ZuluString(), 'duration' => 60],
                ] : [];

                return FakePluginHttp::response(['result' => 20200, 'commands' => [], 'messages' => [], 'data' => ['items' => $items, 'total_pages' => 1, 'total_entries' => count($items)]]);
            },
        ]);

        $sessions = (new SplashtopClient('sp-token', 'eu'))->fetchSessions($from, $to);

        $this->assertCount(1, $sessions);
        $this->assertSame('splashtop', $sessions[0]->provider);
        $this->assertSame('333938', $sessions[0]->sessionId);
        $this->assertSame('4a938402feabe3add440fc7cbf3eed14', $sessions[0]->remoteId);
        $this->assertSame('PC-EMPFANG', $sessions[0]->alias);
        $this->assertSame(30, $sessions[0]->minutes());
        $this->assertSame('Scanner eingerichtet · tech@example.test', $sessions[0]->note);

        $this->assertCount(2, $bodies, 'eine Anfrage je Sitzungsart');
        $this->assertSame(['unattended_remote', 'attended_remote'], array_column($bodies, 'service_kinds'));
        $this->assertSame(11125, $bodies[0]['team_id']);
        $this->assertSame('500', $bodies[0]['per_page_size']);
        $this->assertSame('1', $bodies[0]['page']);
        $this->assertSame($from->utc()->format('Y-m-d H:i:s'), $bodies[0]['start_time']);
        $this->assertSame($to->utc()->format('Y-m-d H:i:s'), $bodies[0]['end_time']);
        $this->assertStringContainsString('srs_uuid', $bodies[0]['fields']);
    }

    public function test_window_longer_than_90_days_is_split_and_clamped_to_one_year(): void {
        $to = CarbonImmutable::now()->startOfHour();
        $from = $to->subDays(500);
        $bodies = [];

        FakePluginHttp::fake([
            self::EU . '/api/open/v1/teams/logs/closed_sessions' => function (RequestInterface $request) use (&$bodies) {
                $bodies[] = json_decode((string) $request->getBody(), true);

                return FakePluginHttp::response(['result' => 20200, 'data' => ['items' => [], 'total_pages' => 0, 'total_entries' => 0]]);
            },
        ]);

        (new SplashtopClient('sp-token', 'eu', '11125', 'unattended_remote'))->fetchSessions($from, $to);

        $this->assertCount(5, $bodies, '364 Tage in Stücken zu 90 Tagen');
        $this->assertGreaterThanOrEqual(CarbonImmutable::now()->subDays(365)->getTimestamp(), CarbonImmutable::parse($bodies[0]['start_time'], 'UTC')->getTimestamp());
        $this->assertSame($to->utc()->format('Y-m-d H:i:s'), end($bodies)['end_time']);
        $this->assertSame(11125, $bodies[0]['team_id'], 'konfigurierte Team-ID ohne Kontoabfrage');
    }

    public function test_rejected_result_throws_with_message(): void {
        FakePluginHttp::fake([
            self::EU . '/api/open/v1/users/basic_info' => FakePluginHttp::response(['result' => 403, 'commands' => [], 'messages' => ['Permission denied'], 'data' => ['error' => 'scope']], 200),
        ]);

        $this->expectException(RemoteProviderException::class);
        $this->expectExceptionMessage('Permission denied');
        (new SplashtopClient('sp-token', 'eu'))->probe();
    }

    public function test_region_selects_server(): void {
        $this->assertSame('https://webapi.splashtop.com', (new SplashtopClient('t', 'com'))->baseUrl());
        $this->assertSame('https://webapi.splashtop.nr', (new SplashtopClient('t', 'nr'))->baseUrl());
        $this->assertSame('https://webapi.splashtop.eu', (new SplashtopClient('t', 'unknown'))->baseUrl());
        $this->assertFalse((new SplashtopClient(null))->isConfigured());
    }
}
