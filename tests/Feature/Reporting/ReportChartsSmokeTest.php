<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportChartsSmokeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Chart-Rollout-Gate (Feature 002): jede aufgerüstete Report-Seite rendert
 * mit leerer Organisation OHNE Daten fehlerfrei und enthält mindestens ein
 * Diagramm (<figure>) — bei Leerdaten dessen erklärenden Leerzustand statt
 * einer Null-Achse. Die Routenliste wächst wellenweise mit dem Rollout.
 */
class ReportChartsSmokeTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    /**
     * Report-Routen mit ausgerolltem Chart-Ausbau (wächst je Welle).
     *
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public static function chartRoutes(): array {
        return [
            'overview' => ['reports.index', []],
            'my-year' => ['reports.my-year', []],
            'my-month' => ['reports.my-month', []],
            'month-by-user-team' => ['reports.month-by-user-team', []],
            'work-balance' => ['reports.work-balance', []],
            'attendance' => ['reports.attendance', []],
            'absences' => ['reports.absences', []],
            'sickness' => ['reports.sickness', []],
            'customers' => ['reports.customers', []],
            'customer-project' => ['reports.customer-project', []],
            'project-details' => ['reports.project-details', []],
            'project-inactive' => ['reports.project-inactive', []],
            'economics' => ['reports.economics', []],
            'entry-types' => ['reports.entry-types', []],
            'assets' => ['reports.assets', []],
            'billing' => ['reports.billing', []],
            'external-payouts' => ['reports.external-payouts', []],
            'expenses' => ['reports.expenses', []],
            // Investitionsbericht hängt unter investments.* (module.investments,
            // in der Enterprise-Factory-Org enthalten).
            'investments' => ['investments.report', []],
            'operations' => ['reports.operations', []],
            'sla' => ['reports.sla', []],
            'materials' => ['reports.materials', []],
            'fleet' => ['reports.fleet', []],
            'on-call' => ['reports.on-call', []],
            'coverage' => ['reports.coverage', []],
            'qualifications' => ['reports.qualifications', []],
            'arbzg-compliance' => ['reports.arbzg-compliance', []],
            'compliance-dashboard' => ['reports.compliance.dashboard', []],
            'compliance-history' => ['reports.compliance.history', []],
            'audit-activity' => ['reports.audit-activity', []],
            'data-quality' => ['reports.data-quality', []],
            'safety' => ['reports.safety', []],
            // Bewerbungsbericht hängt unter applications.* (module.applications,
            // in der Enterprise-Factory-Org enthalten).
            'applications' => ['applications.report', []],
            'cohort-comparison' => ['reports.cohort-comparison', []],
            // Entscheidungsanalysen (Phase 53, MVP-465–468).
            'customer-value' => ['reports.customer-value', []],
            'customer-retention' => ['reports.customer-retention', []],
            'utilization' => ['reports.utilization', []],
            'payment-behavior' => ['reports.payment-behavior', []],
            // Lieferantenanalyse (MVP-472).
            'suppliers' => ['reports.suppliers', []],
            // Lieferantenwert (MVP-473).
            'supplier-value' => ['reports.supplier-value', []],
        ];
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('chartRoutes')]
    public function test_report_renders_chart_figure_on_empty_org(string $routeName, array $parameters): void {
        $this->setUpOrganization();
        $admin = $this->orgAdmin();

        $response = $this->actingAs($admin)
            ->withSession($this->dateRangeMonth(2030, 6))
            ->get(route($routeName, $parameters));

        $response->assertOk();
        $response->assertSee('<figure', false);
        $response->assertSee('Noch keine Daten', false);
    }
}
