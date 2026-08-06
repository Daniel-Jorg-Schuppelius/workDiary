<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphAvailabilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\{MsgraphConnection, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Free/Busy im Termin-Dialog (Feature 102, C2): Endpunkt löst Teilnehmer
 * org-gescopt auf, fragt `getSchedule` über die Kalender-Verbindung ab und
 * antwortet nur mit free/busy/unknown (keine Termindetails); der
 * Slot-Baustein erscheint nur bei aktiver Kalender-Verbindung.
 */
final class MsgraphAvailabilityTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        config()->set('plugins.msgraph.enabled', true);
        config()->set('plugins.msgraph.client_id', 'test-client');
        config()->set('plugins.msgraph.client_secret', 'test-secret');
    }

    private function connection(): MsgraphConnection {
        return MsgraphConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-1',
            'status' => MsgraphConnection::STATUS_ACTIVE,
        ]);
    }

    public function test_endpoint_returns_free_busy_per_participant(): void {
        $this->connection();
        $frei = User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'frei@firma.example', 'name' => 'Frida Frei']);
        $belegt = User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'belegt@firma.example', 'name' => 'Bernd Belegt']);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/calendar/getSchedule' => FakePluginHttp::response([
                'value' => [
                    ['scheduleId' => 'frei@firma.example', 'scheduleItems' => []],
                    ['scheduleId' => 'belegt@firma.example', 'scheduleItems' => [['status' => 'busy']]],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('msgraph.availability', [
            'start' => '2026-08-10T09:00',
            'end' => '2026-08-10T10:00',
            'users' => [$frei->sqid, $belegt->sqid],
        ]));

        $response->assertOk();
        $results = collect($response->json('results'))->keyBy('name');
        $this->assertSame('free', $results->get('Frida Frei')['status'] ?? null);
        $this->assertSame('busy', $results->get('Bernd Belegt')['status'] ?? null);
    }

    public function test_endpoint_requires_active_calendar_connection(): void {
        $participant = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->getJson(route('msgraph.availability', [
            'start' => '2026-08-10T09:00',
            'end' => '2026-08-10T10:00',
            'users' => [$participant->sqid],
        ]))->assertStatus(409);
    }

    public function test_event_dialog_offers_availability_check_only_when_connected(): void {
        // Ohne Verbindung: kein Baustein.
        $this->actingAs($this->admin)->get(route('events.create'))
            ->assertOk()
            ->assertDontSee('data-msgraph-availability', false);

        $this->connection();
        $this->actingAs($this->admin)->get(route('events.create'))
            ->assertOk()
            ->assertSee('data-msgraph-availability', false)
            ->assertSee(route('msgraph.availability'), false);
    }
}
