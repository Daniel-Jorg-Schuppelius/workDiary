<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportXlsxExportAuditTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Project\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Excel-Exporte, die nicht über csvWithMetadata laufen, schreiben denselben
 * Audit-Eintrag `report.exported` wie CSV und PDF (MVP-1101).
 */
class ReportXlsxExportAuditTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    /** @return array<string, array{0: string, 1: string}> */
    public static function reports(): array {
        return [
            'Mein Monat' => ['reports.my-month', 'my-month'],
            'Monat pro Mitarbeiter' => ['reports.month-by-user-team', 'month-by-user-team'],
            'Woche pro Mitarbeiter' => ['reports.week-by-user', 'week-by-user'],
            'Kunden & Projekte' => ['reports.customer-project', 'customer-project'],
            'Projekt-Details' => ['reports.project-details', 'project-details'],
            'Inaktive Projekte' => ['reports.project-inactive', 'project-inactive'],
        ];
    }

    #[DataProvider('reports')]
    public function test_xlsx_export_is_audited(string $routeName, string $reportCode): void {
        $this->setUpOrganization();
        $admin = $this->orgAdmin();
        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Acme GmbH']);
        Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Website-Relaunch',
            'status' => ProjectStatus::Active->value,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withSession($this->dateRangeMonth(2030, 4))
            ->get(route($routeName, ['export' => 'xlsx']))
            ->assertOk();

        $formats = AuditLog::query()
            ->where('event', 'report.exported')
            ->get()
            ->filter(static fn(AuditLog $log): bool => ($log->changes['report_code'] ?? null) === $reportCode)
            ->map(static fn(AuditLog $log): mixed => $log->changes['format'] ?? null)
            ->values()
            ->all();

        $this->assertSame(['xlsx'], $formats);
    }
}
