<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCursorPaginationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Plugins\Msgraph\Api\MsgraphTodoClient;
use App\Plugins\Msgraph\Enums\MsgraphConnectionStatus;
use App\Plugins\Msgraph\Models\MsgraphTaskConnection;
use App\Plugins\RemoteSupport\Api\TeamViewerClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Folgeseiten der Plugin-Clients (Konsolidierungs-Audit 2026-10, k1-08): alle
 * laufen über den `CursorPaginator` des api-toolkit — mit Seitenlimit und
 * Abbruch bei wiederholtem Cursor.
 */
final class PluginCursorPaginationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** `json('@odata.nextLink')` las den Punkt als Pfad: die zweite Seite wurde nie geholt. */
    public function test_todo_lists_follow_the_next_link(): void {
        $this->pluginSecret('msgraph', ['enabled' => true, 'client_id' => 'test-client', 'client_secret' => 'test-secret']);
        $connection = MsgraphTaskConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-1',
            'status' => MsgraphConnectionStatus::Active,
        ]);
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/todo/lists*' => static fn (RequestInterface $request) => str_contains((string) $request->getUri(), 'skiptoken')
                ? FakePluginHttp::response(['value' => [['id' => 'list-2', 'displayName' => 'Zweite Seite']]])
                : FakePluginHttp::response(['value' => [['id' => 'list-1', 'displayName' => 'Erste Seite']], '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/todo/lists?$skiptoken=abc']),
        ]);

        $lists = (new MsgraphTodoClient($connection))->lists();

        $this->assertSame(['list-1', 'list-2'], array_column($lists, 'id'));
        $fake->assertSentCount(2);
    }

    public function test_teamviewer_stops_when_the_offset_repeats(): void {
        $fake = FakePluginHttp::fake([
            'https://webapi.teamviewer.com/api/v1/reports/connections*' => FakePluginHttp::response(['records' => [], 'next_offset' => 'same']),
        ]);

        try {
            (new TeamViewerClient('key'))->fetchSessions(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'));
            $this->fail('Ein wiederholter Offset muss den Abruf beenden.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cursor repeated', $e->getMessage());
        }
        $fake->assertSentCount(2);
    }
}
