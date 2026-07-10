<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceWindowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Operations;

use App\Models\{MaintenanceWindow, User};
use App\Services\Operations\MaintenanceWindowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Tests\TestCase;

class MaintenanceWindowTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function freshWindow(MaintenanceWindow $window): MaintenanceWindow {
        return MaintenanceWindow::query()->findOrFail($window->id);
    }

    /** @param array<string, mixed> $overrides */
    private function window(array $overrides = []): MaintenanceWindow {
        return MaintenanceWindow::query()->create(array_merge([
            'scope' => MaintenanceWindow::SCOPE_SYSTEM,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
            'status' => MaintenanceWindow::STATUS_ACTIVE,
            'read_only' => false,
        ], $overrides));
    }

    public function test_active_system_window_locks_out_regular_users_but_not_admins(): void {
        $this->window(['message' => 'DB-Upgrade läuft']);
        $user = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);

        $this->actingAs($user)->get(route('problem-reports.index'))
            ->assertStatus(503)
            ->assertSee('DB-Upgrade läuft');

        $this->actingAs($this->admin)->get(route('problem-reports.index'))->assertOk();
    }

    public function test_read_only_window_allows_reads_blocks_writes(): void {
        $this->window(['read_only' => true]);
        $user = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);

        $this->actingAs($user)->get(route('problem-reports.index'))->assertOk();

        $this->actingAs($user)->post(route('problem-reports.store'), [
            'summary' => 'Test',
            'description' => 'Test',
            'severity' => 'normal',
        ])->assertStatus(503);
    }

    public function test_org_scoped_window_does_not_affect_other_org(): void {
        $this->window([
            'scope' => MaintenanceWindow::SCOPE_ORGANIZATION,
            'organization_id' => $this->admin->organization_id,
        ]);
        $outsider = User::factory()->user()->create(); // andere Org

        $this->actingAs($outsider)->get(route('problem-reports.index'))->assertOk();
    }

    public function test_lifecycle_transitions_and_guards(): void {
        $service = app(MaintenanceWindowService::class);
        $window = $this->window(['status' => MaintenanceWindow::STATUS_PLANNED, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);

        $service->announce($window);
        $this->assertSame(MaintenanceWindow::STATUS_ANNOUNCED, $this->freshWindow($window)->status);
        // Ankündigung erzeugt Betreiber-Aufgabe.
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => 'maintenance_window:' . $window->id, 'status' => 'open']);

        $service->start($this->freshWindow($window));
        $this->assertSame(MaintenanceWindow::STATUS_ACTIVE, $this->freshWindow($window)->status);

        $service->extend($this->freshWindow($window), CarbonImmutable::now()->addHours(3));
        $this->assertSame(MaintenanceWindow::STATUS_EXTENDED, $this->freshWindow($window)->status);

        $service->complete($this->freshWindow($window));
        $this->assertSame(MaintenanceWindow::STATUS_COMPLETED, $this->freshWindow($window)->status);
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => 'maintenance_window:' . $window->id, 'status' => 'resolved']);

        // Terminaler Status: weitere Übergänge verboten.
        $this->expectException(InvalidArgumentException::class);
        $service->start($this->freshWindow($window));
    }

    public function test_scan_tick_moves_windows_through_lifecycle(): void {
        $window = $this->window([
            'status' => MaintenanceWindow::STATUS_ANNOUNCED,
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(30),
        ]);

        Artisan::call('operations:scan');
        $this->assertSame(MaintenanceWindow::STATUS_ACTIVE, $this->freshWindow($window)->status);

        $this->freshWindow($window)->update(['ends_at' => now()->subMinute()]);
        Artisan::call('operations:scan');
        $this->assertSame(MaintenanceWindow::STATUS_COMPLETED, $this->freshWindow($window)->status);
    }

    public function test_upcoming_window_shows_banner_and_scheduler_skip_flag_works(): void {
        MaintenanceWindow::query()->create([
            'scope' => MaintenanceWindow::SCOPE_SYSTEM,
            'announce_from' => now()->subHour(),
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(3),
            'status' => MaintenanceWindow::STATUS_ANNOUNCED,
            'message' => 'Server-Umzug',
        ]);

        $user = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);
        $this->actingAs($user)->get(route('problem-reports.index'))
            ->assertOk()
            ->assertSee('Server-Umzug');

        // systemActiveNow: erst mit wirksamem Fenster true.
        $this->assertFalse(MaintenanceWindow::systemActiveNow());
        $this->window();
        $this->assertTrue(MaintenanceWindow::systemActiveNow());
    }

    public function test_admin_can_plan_window_via_ui(): void {
        $this->actingAs($this->admin)->post(route('admin.maintenance-windows.store'), [
            'scope' => 'system',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
            'announce_from' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'message' => 'Release 1.4',
            'read_only' => '1',
        ])->assertRedirect(route('admin.maintenance-windows.index'));

        $this->assertDatabaseHas('maintenance_windows', [
            'scope' => 'system',
            'message' => 'Release 1.4',
            'read_only' => true,
            'status' => 'planned',
        ]);

        $regular = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);
        $this->actingAs($regular)->get(route('admin.maintenance-windows.index'))->assertForbidden();
    }
}
