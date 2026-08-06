<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphPresenceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\{MsgraphConnection, User};
use App\Plugins\Msgraph\Services\MsgraphPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Teams-Presence auf der Anwesenheitsseite (Feature 102, F): läuft über die
 * Kalender-Verbindung mit erweitertem Scope `Presence.Read.All`; ohne Scope
 * bleibt das Panel still aus. E-Mail→AAD-ID und Presence werden gecacht
 * (Graph-Limit 1.500 Requests/30 s).
 */
final class MsgraphPresenceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
            'email' => 'admin@firma.example',
        ]);

        config()->set('plugins.msgraph.enabled', true);
        config()->set('plugins.msgraph.client_id', 'test-client');
        config()->set('plugins.msgraph.client_secret', 'test-secret');
    }

    private function connection(string $scopes): MsgraphConnection {
        return MsgraphConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-1',
            'status' => MsgraphConnection::STATUS_ACTIVE,
            'scopes' => $scopes,
        ]);
    }

    public function test_presence_service_resolves_ids_and_states(): void {
        $connection = $this->connection('offline_access Calendars.ReadWrite Presence.Read.All User.ReadBasic.All');

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/users/admin%40firma.example*' => FakePluginHttp::response(['id' => 'aad-1']),
            'https://graph.microsoft.com/v1.0/communications/getPresencesByUserId' => FakePluginHttp::response([
                'value' => [['id' => 'aad-1', 'availability' => 'Busy']],
            ]),
        ]);

        $presence = app(MsgraphPresenceService::class)->presenceForUsers(
            $this->organization,
            collect([$this->admin]),
        );

        $this->assertSame(['admin@firma.example' => 'Busy'], $presence);
    }

    public function test_presence_is_silent_without_scope(): void {
        $this->connection('offline_access Calendars.ReadWrite');
        $idle = FakePluginHttp::fake();

        $presence = app(MsgraphPresenceService::class)->presenceForUsers(
            $this->organization,
            collect([$this->admin]),
        );

        $this->assertSame([], $presence);
        $idle->assertNothingSent();
    }

    public function test_attendance_page_shows_panel_only_with_scope(): void {
        // Ohne Verbindung/Scope: kein Panel.
        $this->actingAs($this->admin)->get(route('attendance.index'))
            ->assertOk()
            ->assertDontSee('data-msgraph-presence', false);

        $this->connection('offline_access Calendars.ReadWrite Presence.Read.All User.ReadBasic.All');
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/users/*' => FakePluginHttp::response(['id' => 'aad-1']),
            'https://graph.microsoft.com/v1.0/communications/getPresencesByUserId' => FakePluginHttp::response([
                'value' => [['id' => 'aad-1', 'availability' => 'Available']],
            ]),
        ]);

        $this->actingAs($this->admin)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('data-msgraph-presence', false)
            ->assertSee($this->admin->name);
    }
}
