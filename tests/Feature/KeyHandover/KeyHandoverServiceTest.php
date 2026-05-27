<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KeyHandoverServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\KeyHandover;

use App\Enums\KeyHandover\KeyHandoverDirection;
use App\Models\{Asset, Organization, User};
use App\Services\KeyHandover\KeyHandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KeyHandoverServiceTest extends TestCase {
    use RefreshDatabase;

    private KeyHandoverService $service;

    private Organization $org;

    private User $actor;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();

        $this->service = new KeyHandoverService;
        $this->org = Organization::factory()->create();
        $this->actor = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $this->org->id,
        ]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($this->actor);
        app()->instance('currentOrganization', $this->org);
    }

    public function test_record_creates_handover_and_audits(): void {
        Carbon::setTestNow('2026-06-01 09:00:00');

        $handover = $this->service->record($this->asset, $this->actor, [
            'direction' => KeyHandoverDirection::Out->value,
            'person_name' => 'Max Mustermann',
            'occurred_at' => '2026-06-01 09:00:00',
        ]);

        $this->assertSame(KeyHandoverDirection::Out, $handover->direction);
        $this->assertSame('Max Mustermann', $handover->person_name);
        $this->assertSame($this->actor->id, $handover->handed_by_user_id);
        $this->assertNull($handover->returned_to_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'key_handover.recorded',
            'auditable_id' => $handover->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_current_holder_returns_latest_open_handover(): void {
        $out = $this->service->record($this->asset, $this->actor, [
            'direction' => KeyHandoverDirection::Out->value,
            'person_name' => 'Anna',
            'occurred_at' => '2026-06-01 09:00:00',
        ]);

        $this->assertNotNull($this->service->currentHolder($this->asset));
        $this->assertSame($out->id, $this->service->currentHolder($this->asset)?->id);

        $this->service->record($this->asset, $this->actor, [
            'direction' => KeyHandoverDirection::In->value,
            'person_name' => 'Anna',
            'occurred_at' => '2026-06-02 09:00:00',
        ]);

        $this->assertNull($this->service->currentHolder($this->asset));
    }
}
