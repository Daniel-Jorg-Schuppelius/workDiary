<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportSuggestionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Asset\AssetClass;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Asset, Customer, RemotePendingSession, TimeEntry, User};
use App\Plugins\RemoteSupport\Providers\TeamViewerClient;
use App\Plugins\RemoteSupport\{RemoteSupportService, RemoteSupportSuggestionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class RemoteSupportSuggestionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();
    }

    private function suggester(): RemoteSupportSuggestionService {
        return new RemoteSupportSuggestionService(new RemoteSupportService);
    }

    private function pendingSession(string $remoteId, string $sessionId, string $start, string $end, ?string $alias = null, ?int $assetId = null): RemotePendingSession {
        return RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $assetId,
            'provider' => TeamViewerClient::ID,
            'remote_id' => $remoteId,
            'alias' => $alias,
            'session_id' => $sessionId,
            'started_at' => $start,
            'ended_at' => $end,
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);
    }

    private function timeEntryFor(Customer $customer, string $start, string $end): TimeEntry {
        $project = $customer->defaultProjectOrCreate();

        return TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->owner->id,
            'date' => substr($start, 0, 10),
            'started_at' => $start,
            'ended_at' => $end,
            'kind' => TimeEntryKind::Work,
            'description' => 'Erfasste Arbeit',
            'billable' => true,
        ]);
    }

    /** @return array<string, object> */
    private function suggestionsForOpenGroups(): array {
        $groups = (new RemoteSupportService)->openPendingGroups($this->organization);

        return $this->suggester()->suggestForGroups($this->organization, $groups);
    }

    public function test_dominant_overlap_suggests_customer(): void {
        $customerA = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha GmbH']);
        $customerB = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beta AG']);

        // Zwei von drei Sitzungen liegen in erfassten Zeiten für Alpha.
        $this->timeEntryFor($customerA, '2026-07-20 09:00:00', '2026-07-20 12:00:00');
        $this->timeEntryFor($customerB, '2026-07-21 15:00:00', '2026-07-21 16:00:00');

        $this->pendingSession('111000111', 's1', '2026-07-20 09:30:00', '2026-07-20 10:15:00');
        $this->pendingSession('111000111', 's2', '2026-07-20 11:00:00', '2026-07-20 11:40:00');
        $this->pendingSession('111000111', 's3', '2026-07-22 09:00:00', '2026-07-22 09:30:00');

        $suggestions = $this->suggestionsForOpenGroups();

        $key = TeamViewerClient::ID . '|111000111';
        $this->assertArrayHasKey($key, $suggestions);
        $this->assertSame('customer', $suggestions[$key]->kind);
        $this->assertSame($customerA->sqid, $suggestions[$key]->customerSqid);
        $this->assertSame(2, $suggestions[$key]->matched);
        $this->assertSame(3, $suggestions[$key]->total);
    }

    public function test_two_customers_with_substantial_overlap_suggest_shared_device(): void {
        $customerA = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha GmbH']);
        $customerB = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beta AG']);

        $this->timeEntryFor($customerA, '2026-07-20 09:00:00', '2026-07-20 12:00:00');
        $this->timeEntryFor($customerA, '2026-07-21 09:00:00', '2026-07-21 12:00:00');
        $this->timeEntryFor($customerB, '2026-07-20 14:00:00', '2026-07-20 17:00:00');
        $this->timeEntryFor($customerB, '2026-07-21 14:00:00', '2026-07-21 17:00:00');

        $this->pendingSession('222000222', 's1', '2026-07-20 09:30:00', '2026-07-20 10:15:00');
        $this->pendingSession('222000222', 's2', '2026-07-21 10:00:00', '2026-07-21 10:40:00');
        $this->pendingSession('222000222', 's3', '2026-07-20 15:00:00', '2026-07-20 15:45:00');
        $this->pendingSession('222000222', 's4', '2026-07-21 16:00:00', '2026-07-21 16:30:00');

        $suggestions = $this->suggestionsForOpenGroups();

        $key = TeamViewerClient::ID . '|222000222';
        $this->assertArrayHasKey($key, $suggestions);
        $this->assertSame('shared', $suggestions[$key]->kind);
        $this->assertNull($suggestions[$key]->customerSqid);
    }

    public function test_learned_alias_token_from_assigned_device_suggests_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Gebr. Schwabenland Großküchen']);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => 'workstation',
            'customer_id' => $customer->id,
            'name' => 'GSL-DC01',
        ]);
        (new RemoteSupportService)->setRemoteId($asset, TeamViewerClient::ID, '999888777');

        // Unbekanntes Gerät mit demselben Kürzel im Alias, ohne Zeitüberlappung.
        $this->pendingSession('333000333', 's1', '2026-07-20 09:00:00', '2026-07-20 09:30:00', alias: 'GSL-Empfang');

        $suggestions = $this->suggestionsForOpenGroups();

        $key = TeamViewerClient::ID . '|333000333';
        $this->assertArrayHasKey($key, $suggestions);
        $this->assertSame('customer', $suggestions[$key]->kind);
        $this->assertSame($customer->sqid, $suggestions[$key]->customerSqid);
        // Das gelernte Kürzel wird zum Hinterlegen als Matchcode angeboten.
        $this->assertSame('GSL', $suggestions[$key]->matchcode);
    }

    public function test_matchcode_beats_name_pattern(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Gebr. Schwabenland Großküchen']);
        $withCode = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ganz anderer Name', 'matchcode' => 'GSL']);

        $this->pendingSession('444000444', 's1', '2026-07-20 09:00:00', '2026-07-20 09:30:00', alias: 'GSL-Kasse');

        $suggestions = $this->suggestionsForOpenGroups();

        $key = TeamViewerClient::ID . '|444000444';
        $this->assertArrayHasKey($key, $suggestions);
        $this->assertSame($withCode->sqid, $suggestions[$key]->customerSqid);
        // Kunde hat bereits einen Matchcode — nichts erneut anbieten.
        $this->assertNull($suggestions[$key]->matchcode);
    }

    public function test_subsequence_matches_abbreviated_customer_name(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Gebr. Schwabenland Großküchen']);
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beta AG']);

        $this->pendingSession('555000555', 's1', '2026-07-20 09:00:00', '2026-07-20 09:30:00', alias: 'GSL-Lohn');

        $suggestions = $this->suggestionsForOpenGroups();

        $key = TeamViewerClient::ID . '|555000555';
        $this->assertArrayHasKey($key, $suggestions);
        $this->assertSame('customer', $suggestions[$key]->kind);
        $this->assertSame($customer->sqid, $suggestions[$key]->customerSqid);
        $this->assertSame('GSL', $suggestions[$key]->matchcode);
    }

    public function test_no_signals_no_suggestion(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha GmbH']);

        $this->pendingSession('666000666', 's1', '2026-07-20 09:00:00', '2026-07-20 09:30:00');

        $this->assertSame([], $this->suggestionsForOpenGroups());
    }

    public function test_single_free_asset_of_customer_is_suggested(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha GmbH']);
        $free = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => 'workstation',
            'customer_id' => $customer->id,
            'name' => 'Empfangs-PC',
        ]);

        $this->timeEntryFor($customer, '2026-07-20 09:00:00', '2026-07-20 12:00:00');
        $this->pendingSession('777000777', 's1', '2026-07-20 09:30:00', '2026-07-20 10:15:00');

        $suggestions = $this->suggestionsForOpenGroups();

        $key = TeamViewerClient::ID . '|777000777';
        $this->assertArrayHasKey($key, $suggestions);
        $this->assertSame($free->sqid, $suggestions[$key]->assetSqid);
    }

    public function test_shared_sessions_get_per_session_customer_suggestion(): void {
        $customerA = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha GmbH']);
        $customerB = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beta AG']);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => null,
            'shared_remote' => true,
        ]);

        $this->timeEntryFor($customerA, '2026-07-20 09:00:00', '2026-07-20 12:00:00');
        $this->timeEntryFor($customerB, '2026-07-20 14:00:00', '2026-07-20 17:00:00');

        $sessionA = $this->pendingSession('888000888', 's1', '2026-07-20 09:30:00', '2026-07-20 10:15:00', assetId: $asset->id);
        $sessionB = $this->pendingSession('888000888', 's2', '2026-07-20 15:00:00', '2026-07-20 15:45:00', assetId: $asset->id);
        $none = $this->pendingSession('888000888', 's3', '2026-07-22 09:00:00', '2026-07-22 09:30:00', assetId: $asset->id);

        $devices = (new RemoteSupportService)->openSharedSessions($this->organization);
        $suggestions = $this->suggester()->suggestForSharedSessions($this->organization, $devices);

        $this->assertSame($customerA->sqid, $suggestions[$sessionA->id]->customerSqid ?? null);
        $this->assertSame($customerB->sqid, $suggestions[$sessionB->id]->customerSqid ?? null);
        $this->assertArrayNotHasKey($none->id, $suggestions);
    }

    public function test_assign_new_persists_matchcode_on_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Gebr. Schwabenland Großküchen']);

        $this->pendingSession('999000999', 's1', '2026-07-20 09:00:00', '2026-07-20 09:30:00', alias: 'GSL-Buchhaltung');

        $response = $this->actingAs($this->orgAdmin())->post(route('admin.remote-support.pending.assign-new'), [
            'provider' => TeamViewerClient::ID,
            'remote_id' => '999000999',
            'name' => 'GSL-Buchhaltung',
            'category_code' => 'workstation',
            'customer_id' => $customer->sqid,
            'matchcode' => 'GSL',
        ]);

        $response->assertRedirect();
        $this->assertSame('GSL', $customer->fresh()->matchcode);
    }

    public function test_assign_new_skips_matchcode_when_already_taken(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Anderer Kunde', 'matchcode' => 'GSL']);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Gebr. Schwabenland Großküchen']);

        $this->pendingSession('121212121', 's1', '2026-07-20 09:00:00', '2026-07-20 09:30:00');

        $response = $this->actingAs($this->orgAdmin())->post(route('admin.remote-support.pending.assign-new'), [
            'provider' => TeamViewerClient::ID,
            'remote_id' => '121212121',
            'name' => 'Irgendein PC',
            'category_code' => 'workstation',
            'customer_id' => $customer->sqid,
            'matchcode' => 'GSL',
        ]);

        $response->assertRedirect();
        $this->assertNull($customer->fresh()->matchcode);
    }
}
