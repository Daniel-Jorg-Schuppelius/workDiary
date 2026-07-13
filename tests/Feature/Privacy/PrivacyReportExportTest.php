<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyReportExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Models\{AuditLog, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MVP-327 (§3.9): Datenschutzbericht als PDF über `admin.privacy.report`
 * inkl. Audit-Event `privacy.report.exported` (Hash-Kette, Eloquent-Weg).
 */
class PrivacyReportExportTest extends TestCase {
    use RefreshDatabase;

    public function test_report_requires_authentication(): void {
        $this->get(route('admin.privacy.report'))->assertRedirect(route('login'));
    }

    public function test_report_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('admin.privacy.report'))
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'privacy.report.exported']);
    }

    public function test_report_returns_pdf_with_dated_filename(): void {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.privacy.report'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString(
            sprintf('datenschutzbericht-%d-%s.pdf', $admin->organization_id, now()->format('Y-m-d')),
            $disposition,
        );
    }

    public function test_report_export_writes_audit_event_with_actor_and_org(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.privacy.report'))
            ->assertOk();

        /** @var AuditLog|null $log */
        $log = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('event', 'privacy.report.exported')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame((int) $admin->organization_id, (int) $log->organization_id);
        $this->assertSame((int) $admin->id, (int) $log->user_id);

        // `changes` bewusst via getAttribute lesen (Kollision mit Eloquents
        // internem Dirty-Tracking, siehe AuditLog::hashPayload()).
        $changes = (array) $log->getAttribute('changes');
        $this->assertSame('privacy_report', $changes['filter'] ?? null);
        $this->assertArrayHasKey('generated_at', $changes);
        $this->assertIsInt($changes['row_count'] ?? null);
    }

    public function test_report_audit_event_is_scoped_to_own_org_only(): void {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create(); // eigene Org via Factory-Default

        $this->actingAs($admin)
            ->get(route('admin.privacy.report'))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'privacy.report.exported',
            'organization_id' => $admin->organization_id,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'privacy.report.exported',
            'organization_id' => $otherAdmin->organization_id,
        ]);
    }
}
