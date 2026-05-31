<?php
/*
 * Created on   : Sat Nov 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Models\{AuditLog, User};
use App\Services\Privacy\PrivacyOverviewService;
use CommonToolkit\Helper\Data\JsonHelper;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyExportTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_export_requires_authentication(): void {
        $this->get(route('admin.privacy.export'))->assertRedirect(route('login'));
    }

    public function test_export_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('admin.privacy.export'))
            ->assertForbidden();
    }

    public function test_export_rejects_unknown_format(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.privacy.export', ['format' => 'xml']))
            ->assertStatus(422);
    }

    public function test_export_json_returns_attachment_and_writes_audit(): void {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.privacy.export', ['format' => 'json']));

        $response->assertOk();
        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.json', (string) $response->headers->get('Content-Disposition'));

        $payload = JsonHelper::decode((string) $response->getContent());
        $this->assertIsArray($payload);
        $this->assertSame($admin->organization_id, $payload['organization']['id']);
        $this->assertArrayHasKey('sessions', $payload);
        $this->assertArrayHasKey('tokens', $payload);
        $this->assertArrayHasKey('categories', $payload);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'privacy.overviewExported',
        ]);
    }

    public function test_export_csv_returns_streamed_csv(): void {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.privacy.export', ['format' => 'csv']));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $this->assertStringContainsString('section,id,user_id,event,extra', $body);
    }

    public function test_service_aggregates_sessions_tokens_exports_and_support(): void {
        $admin = User::factory()->admin()->create();

        AuditLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'tenant.export.csv',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => ['rows' => 3],
        ]);
        AuditLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'support.access.granted',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => ['by' => 'platform'],
        ]);

        $service = app(PrivacyOverviewService::class);
        $data = $service->forUser($admin, $admin->organization);

        $this->assertGreaterThanOrEqual(1, $data['exports']->count());
        $this->assertGreaterThanOrEqual(1, $data['support_accesses']->count());
        $this->assertTrue($data['can']['report_export']);

        $payload = $service->toExportPayload($data);
        $this->assertSame($admin->organization_id, $payload['organization']['id']);
        $this->assertSame((int) $data['member_count'], (int) $payload['member_count']);
        $this->assertGreaterThanOrEqual(1, count($payload['exports']));
        $this->assertGreaterThanOrEqual(1, count($payload['support_accesses']));
    }
}
