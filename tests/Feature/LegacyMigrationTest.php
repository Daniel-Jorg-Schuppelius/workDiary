<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LegacyMigrationTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_legacy_write_blocked_by_default(): void {
        Config::set('app.legacy_write_enabled', false);
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->post(route('legacy.diary.store'), [
            'inhalt' => 'Test',
            'gelesen' => 2,
        ]);

        // Middleware fängt vor Validierung ab und redirected zurück.
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_legacy_read_routes_still_accessible_when_blocked(): void {
        Config::set('app.legacy_write_enabled', false);
        $user = User::factory()->admin()->create();

        // Index sollte zumindest nicht durch das Write-Block-Middleware gestoppt werden.
        // (Kann 500 werfen wenn Legacy-DB fehlt — aber NICHT 423/Redirect mit error.)
        $response = $this->actingAs($user)->get(route('legacy.diary.index'));
        $this->assertNotEquals(423, $response->getStatusCode());
        if ($response->isRedirect()) {
            $this->assertNull(session('error'));
        }
    }

    public function test_migration_dashboard_requires_admin(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user)
            ->get(route('admin.legacy-migration.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_migration_dashboard(): void {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.legacy-migration.index'))
            ->assertOk()
            ->assertSee('Legacy-Migration');
    }
}
