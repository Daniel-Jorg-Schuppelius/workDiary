<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportReportControllerTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_index_requires_authentication(): void {
        $this->get(route('admin.support.report.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('admin.support.report.index'))->assertForbidden();
    }

    public function test_index_renders_preview_for_org_admin(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.support.report.index'))
            ->assertOk()
            ->assertSee(__('Supportbericht'))
            ->assertSee(__('Inhalts-Übersicht'));
    }

    public function test_generate_creates_zip_and_writes_audit(): void {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.support.report.generate'));

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type') ?? $response->headers->get('Content-Type'));

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'support.reportGenerated',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'support.reportDownloaded',
        ]);
    }

    public function test_generate_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('admin.support.report.generate'))
            ->assertForbidden();
    }

    public function test_json_download_returns_json_file_and_writes_audit(): void {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.support.report.download'));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('support-report-', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.json', (string) $response->headers->get('Content-Disposition'));

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('release', $payload);
        $this->assertArrayHasKey('health', $payload);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => 'support.reportDownloaded',
        ]);
    }

    public function test_json_download_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('admin.support.report.download'))
            ->assertForbidden();
    }

    public function test_browser_preview_returns_json_payload(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.support.report.preview'))
            ->assertOk()
            ->assertJsonStructure(['installation', 'release', 'health', 'plugin_errors', 'operations']);
    }

    public function test_browser_preview_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('admin.support.report.preview'))
            ->assertForbidden();
    }

    public function test_json_download_excludes_customer_and_app_key(): void {
        $admin = User::factory()->admin()->create();
        $customer = \App\Models\Customer::factory()->create([
            'organization_id' => $admin->organization_id,
            'created_by' => $admin->id,
            'name' => 'Negativ-Test-Kunde-ABC-XYZ',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.support.report.download'));
        $body = (string) $response->getContent();

        $this->assertStringNotContainsString($customer->name, $body);
        $this->assertStringNotContainsString((string) config('app.key'), $body);
        $this->assertStringNotContainsString($admin->email, $body);
    }
}
