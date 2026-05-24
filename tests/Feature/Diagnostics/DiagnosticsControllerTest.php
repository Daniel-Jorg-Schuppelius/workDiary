<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiagnosticsControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Diagnostics;

use App\Models\{AuditLog, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DiagnosticsControllerTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_index_requires_authentication(): void {
        $this->get(route('admin.diagnostics.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('admin.diagnostics.index'))->assertForbidden();
    }

    public function test_index_renders_for_org_admin_and_writes_audit(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.diagnostics.index'))
            ->assertOk()
            ->assertSee(__('Diagnose'));

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'diagnostics.viewed',
        ]);
    }

    public function test_json_endpoint_returns_machine_readable_report(): void {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson(route('admin.diagnostics.json'));

        $response->assertOk()
            ->assertJsonStructure([
                'generated_at',
                'overall_status',
                'sections' => [['code', 'status', 'metrics', 'messages', 'checked_at']],
            ])
            ->assertJsonPath('sections.0.code', 'version');
    }

    public function test_test_mail_requires_run_check_permission(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->postJson(route('admin.diagnostics.test-mail'))
            ->assertForbidden();
    }

    public function test_test_mail_for_admin_dispatches_and_audits(): void {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.diagnostics.test-mail'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('target', $admin->email);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'diagnostics.testTriggered',
        ]);
    }
}
