<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceModeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Organization;

use App\Models\{AttendanceTerminal, AuditLog, User, UserBadge};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rang 65: Wartungsmodus pro Mandant — Nicht-Admins erhalten 503 mit
 * Retry-After, Admins arbeiten weiter (Banner), Terminal-Ingest läuft
 * standardmäßig weiter (block_ingest optional), Umschaltung wird auditiert.
 */
final class MaintenanceModeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @param array<string, mixed> $maintenance */
    private function enableMaintenance(array $maintenance = []): void {
        $this->organization->update([
            'settings' => array_replace((array) $this->organization->settings, [
                'maintenance' => array_replace(['enabled' => '1'], $maintenance),
            ]),
        ]);
    }

    public function test_non_admin_gets_503_with_retry_after(): void {
        $this->enableMaintenance(['message' => 'Wir warten die Anlage.']);
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->get(route('today.show'));

        $response->assertStatus(503);
        $response->assertHeader('Retry-After');
        $response->assertSee('Wir warten die Anlage.');
    }

    public function test_admin_keeps_working_and_sees_banner(): void {
        $this->enableMaintenance();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)->get(route('today.show'));

        $response->assertOk();
        $response->assertSee('data-maintenance-banner', false);
    }

    public function test_expired_until_disables_maintenance(): void {
        $this->enableMaintenance(['until' => now()->subHour()->format('Y-m-d\TH:i')]);
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('today.show'))->assertOk();
    }

    public function test_guest_still_reaches_login(): void {
        $this->enableMaintenance();

        $this->get(route('login'))->assertOk();
    }

    public function test_terminal_ingest_keeps_running_unless_blocked(): void {
        $this->enableMaintenance();
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        [, $token] = AttendanceTerminal::issue($this->organization->id, 'Halle Nord');
        UserBadge::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'label' => 'Test',
            'badge_hash' => UserBadge::hashBadge('AB12'),
        ]);

        // Default: Stempeln läuft während der Wartung weiter.
        $this->postJson('/api/terminal/ingest/' . $token, ['badge_uid' => 'AB12'])
            ->assertOk()->assertJsonPath('status', 'clocked_in');

        // Mit block_ingest pausiert der Ingest (503 + Retry-After).
        $this->enableMaintenance(['block_ingest' => '1']);
        $this->postJson('/api/terminal/ingest/' . $token, ['badge_uid' => 'AB12'])
            ->assertStatus(503)->assertHeader('Retry-After');
    }

    public function test_toggle_is_audited(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->put(route('admin.organizations.update', $this->organization), [
            'name' => $this->organization->name,
            'plan' => $this->organization->plan,
            'locale' => $this->organization->locale ?? 'de',
            'timezone' => $this->organization->timezone ?? 'Europe/Berlin',
            'is_active' => 1,
            'settings' => ['maintenance' => ['enabled' => '1', 'message' => 'Kurz weg.']],
        ])->assertRedirect(route('admin.organizations.index'));

        $this->assertTrue($this->organization->refresh()->inMaintenance());
        $this->assertSame(1, AuditLog::query()->where('event', 'organization.maintenance_toggled')->count());
    }
}
