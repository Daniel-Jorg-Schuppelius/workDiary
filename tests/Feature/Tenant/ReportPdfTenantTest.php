<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportPdfTenantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Enums\Project\ProjectStatus;
use App\Models\{Customer, Organization, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PDFToolkit\Registries\PDFReaderRegistry;
use Tests\Concerns\WithGlobalDateRange;
use Tests\TestCase;

/**
 * Systematischer Cross-Org-Tenant-Test über die Reporting-PDF-Exporte
 * (Bauturbo A17, MVP-335). Quelle: ../WorkDiary-Architecture/security/
 * tenant-audit-2026.md:277 ("18 Report-PDFs — bislang nur stichprobenartig
 * getestet"); Standardpattern siehe adr-export-authorization.md.
 *
 * Vorgehen: Organisation B erhält markante Fixtures (User-/Kunden-/Projekt-
 * Marker + Zeiteintrag). Ein Admin der Organisation A ruft jede Report-Route
 * einmal als HTML und einmal mit `?export=pdf` auf — der PDF-Text (Extraktion
 * über das pdf-toolkit, PDFReaderRegistry) und die HTML-Antwort dürfen keine
 * Kennungen der Fremd-Org enthalten; der Renderer läuft dabei komplett unter
 * dem Org-Scope des angemeldeten Users.
 */
class ReportPdfTenantTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;

    /** Kennungen der Org-B-Fixtures — dürfen in Org-A-Exporten nie auftauchen. */
    private const MARKERS = ['ZZLEAK-USER-B', 'ZZLEAK-CUST-B', 'ZZLEAK-PROJ-B'];

    /**
     * Reporting-Routen mit `?export=pdf`-Zweig (RendersReportPdf-Trait).
     * Liste hier pflegen, wenn Reports hinzukommen — Quelle tenant-audit:277.
     * Param-gebundene Drilldowns (reports.customers.drilldown.*,
     * reports.entry-types.drilldown.*, reports.assets.drilldown.*,
     * reports.project-details mit ?project=) laufen über dieselben
     * org-gescopten Controller-Queries und werden hier bewusst nicht
     * einzeln aufgezählt.
     */
    private const PDF_REPORT_ROUTES = [
        'reports.absences',
        'reports.arbzg-compliance',
        'reports.assets',
        'reports.attendance',
        'reports.audit-activity',
        'reports.billing',
        'reports.coverage',
        'reports.customers',
        'reports.customer-project',
        'reports.economics',
        'reports.entry-types',
        'reports.fleet',
        'reports.materials',
        'reports.month-by-user-team',
        'reports.my-month',
        'reports.on-call',
        'reports.operations',
        'reports.qualifications',
        'reports.sla',
        'reports.week-by-user',
        'reports.work-balance',
    ];

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $userB;

    protected function setUp(): void {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['slug' => 'rep-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'rep-b']);

        $this->adminA = User::factory()->admin()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Reporter A',
        ]);

        // Org-B-Fixtures mit eindeutigen Markern über die gängigsten
        // Report-Dimensionen (Mitarbeiter, Kunde, Projekt, Zeiteintrag).
        $this->withOrg($this->orgB, function (): void {
            $userB = $this->userB = User::factory()->user()->create([
                'organization_id' => $this->orgB->id,
                'name' => 'ZZLEAK-USER-B',
            ]);
            $customerB = Customer::create([
                'organization_id' => $this->orgB->id,
                'name' => 'ZZLEAK-CUST-B',
            ]);
            $projectB = Project::create([
                'organization_id' => $this->orgB->id,
                'customer_id' => $customerB->id,
                'name' => 'ZZLEAK-PROJ-B',
                'status' => ProjectStatus::Active->value,
                'created_by' => $userB->id,
            ]);
            TimeEntry::factory()->create([
                'organization_id' => $this->orgB->id,
                'project_id' => $projectB->id,
                'user_id' => $userB->id,
                'date' => now()->toDateString(),
                'minutes' => 240,
            ]);
        });
    }

    public function test_report_pdf_exports_do_not_leak_cross_org_data(): void {
        $session = $this->dateRangeSession(now()->subMonth()->toDateString(), now()->addMonth()->toDateString());

        foreach (self::PDF_REPORT_ROUTES as $routeName) {
            $response = $this->actingAs($this->adminA)
                ->withSession($session)
                ->get(route($routeName, ['export' => 'pdf']));

            $this->assertSame(200, $response->status(), "Report [{$routeName}] mit export=pdf lieferte HTTP " . $response->status() . '.');
            $this->assertStringStartsWith(
                'application/pdf',
                (string) $response->headers->get('Content-Type'),
                "Report [{$routeName}] lieferte kein PDF.",
            );

            $text = $this->extractPdfText($response, $routeName);
            foreach (self::MARKERS as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $text,
                    "TENANT-LEAK: Report-PDF [{$routeName}] enthält die Fremd-Org-Kennung [{$marker}].",
                );
            }
        }
    }

    public function test_report_html_views_do_not_leak_cross_org_data(): void {
        $session = $this->dateRangeSession(now()->subMonth()->toDateString(), now()->addMonth()->toDateString());

        foreach (self::PDF_REPORT_ROUTES as $routeName) {
            $response = $this->actingAs($this->adminA)
                ->withSession($session)
                ->get(route($routeName));

            $this->assertSame(200, $response->status(), "Report [{$routeName}] (HTML) lieferte HTTP " . $response->status() . '.');
            $body = (string) $response->getContent();
            foreach (self::MARKERS as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $body,
                    "TENANT-LEAK: Report [{$routeName}] (HTML) enthält die Fremd-Org-Kennung [{$marker}].",
                );
            }
        }
    }

    /**
     * Regressionscheck der beim Bauturbo-A17-Sweep gefundenen (und minimal
     * gefixten) User-Listen-Leaks, die der Standard-Sweep oben nicht trifft:
     * Anwesenheits-Report im Team-Scope, Externen-Vergütungsreport und die
     * Plan/Ist-Personen-Sicht mit fremder User-Sqid (User-Modell hat KEINEN
     * globalen OrganizationScope — Filter muss explizit gesetzt sein).
     */
    public function test_user_list_report_variants_do_not_leak_cross_org_users(): void {
        $session = $this->dateRangeSession(now()->subMonth()->toDateString(), now()->addMonth()->toDateString());

        $variants = [
            ['reports.attendance', ['scope' => 'team']],
            ['reports.external-payouts', []],
            ['reports.plan-ist.organization', []],
            // Fremde User-Sqid: darf NICHT auflösen → eigene Sicht ohne Marker.
            ['reports.plan-ist.presence', ['user' => $this->userB->sqid]],
        ];

        foreach ($variants as [$routeName, $params]) {
            $response = $this->actingAs($this->adminA)
                ->withSession($session)
                ->get(route($routeName, $params));

            $this->assertSame(200, $response->status(), "Report-Variante [{$routeName}] lieferte HTTP " . $response->status() . '.');
            $body = (string) $response->getContent();
            foreach (self::MARKERS as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $body,
                    "TENANT-LEAK: Report-Variante [{$routeName}] enthält die Fremd-Org-Kennung [{$marker}].",
                );
            }
        }
    }

    /**
     * Extrahiert den Text eines PDF-Responses über das pdf-toolkit
     * (PDFReaderRegistry → pdftotext & Co.) — die Textströme im PDF sind
     * komprimiert, ein Substring-Check auf den Roh-Bytes wäre wertlos.
     */
    private function extractPdfText(TestResponse $response, string $routeName): string {
        $path = tempnam(sys_get_temp_dir(), 'wd-report-') . '.pdf';
        file_put_contents($path, (string) $response->getContent());

        try {
            $document = PDFReaderRegistry::getInstance()->extractTextOnly($path);
            $text = $document->getTextOrDefault();
        } catch (\Throwable $e) {
            $this->fail("PDF-Textextraktion für [{$routeName}] fehlgeschlagen (Reader/pdftotext verfügbar?): " . $e->getMessage());
        } finally {
            @unlink($path);
        }

        return $text;
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
