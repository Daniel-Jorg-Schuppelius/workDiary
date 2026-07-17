<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimsReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\{AuditLog, Customer, User};
use App\Services\Claims\ClaimCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Qualitätsbericht Reklamationen (Feature 072, MVP-254): CSV-Export nach
 * Report-Konvention (MVP-043) — ?export=csv, BOM + Metazeilen
 * (#report:/#generated:/#filter_hash:) und Audit report.exported.
 */
final class ClaimsReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_report_view_links_export_csv(): void {
        $this->actingAs($this->admin)
            ->get(route('claims.reports.index'))
            ->assertOk()
            ->assertSee('export=csv');
    }

    public function test_csv_export_has_bom_meta_lines_and_audit(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        app(ClaimCaseService::class)->open($this->organization, $this->admin, [
            'title' => 'Heizungsventil undicht',
            'source' => 'manual',
            'priority' => 'normal',
            'severity' => 'minor',
            'customer_id' => $customer->id,
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->toDateString();
        $response = $this->actingAs($this->admin)
            ->get(route('claims.reports.index', ['from' => $from, 'to' => $to, 'export' => 'csv']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = (string) $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('#report:claims-quality', $body);
        $this->assertStringContainsString('#generated:', $body);
        $this->assertMatchesRegularExpression('/#filter_hash:[0-9a-f]{8}/', $body);
        $this->assertStringContainsString('Kennzahl', $body);
        $this->assertStringContainsString('total', $body);

        $audit = AuditLog::query()->where('event', 'report.exported')->latest('id')->firstOrFail();
        $this->assertSame($this->organization->id, $audit->organization_id);
        $this->assertSame($this->admin->id, $audit->user_id);
        $changes = $audit->getAttribute('changes') ?? [];
        $this->assertSame('claims-quality', $changes['report_code']);
        $this->assertSame('csv', $changes['format']);
        // resolveRange honoriert explizite from/to-Parameter (Bookmarks).
        $this->assertSame(['from' => $from, 'to' => $to], $changes['filters']);
        $fullHash = (string) $changes['filter_hash'];
        $this->assertSame(64, strlen($fullHash));
        $this->assertStringContainsString('#filter_hash:' . substr($fullHash, 0, 8), $body);
    }
}
