<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssuerBlocksAllKindsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\{InformationBlock, RenderDocumentKind};
use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Models\Club\ClubMember;
use App\Models\DocumentDesign\DocumentRenderSnapshot;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\{Organization, User};
use App\Models\Safety\HazardAssessment;
use App\Services\Club\{ClubGradeCertificatePdfRenderer, ClubGradingService};
use App\Services\Construction\{ConstructionNoticePdfRenderer, ConstructionNoticeService};
use App\Services\DocumentDesign\{DocumentDesignRenderer, RenderPreflightService, RenderProfileService};
use App\Services\Learning\{LearningCertificatePdfRenderer, LearningCompletionService, LearningCourseService, LearningEnrollmentService, LearningReportCardPdfRenderer};
use App\Services\Manufacturing\ManufacturingRecordPdfRenderer;
use App\Services\Safety\{SafetyEvidencePdfRenderer, SafetyInstructionService};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage, View};
use Tests\TestCase;

/**
 * MVP-1624: Jede Dokumentart mit Pflichtblock Firmenangaben druckt ihn nach
 * Blockzustand, und der Preflight prüft die Stammdaten dafür. Vorher
 * abgeschlossene, versandte oder unterschriebene Belege bleiben, wie sie waren.
 */
final class IssuerBlocksAllKindsTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    /** @var array<string, array<string, mixed>> */
    private array $captured = [];

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->org = Organization::factory()->withIssuerIdentity()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        View::composer([
            'pdf.construction-notice', 'pdf.manufacturing-record', 'pdf.safety-instruction', 'pdf.safety-assessment',
            'learning.pdf.certificate', 'learning.pdf.report-card', 'club.pdf.grade_certificate',
        ], function (\Illuminate\View\View $view): void {
            $this->captured[$view->name()] = $view->getData();
        });
    }

    public function test_every_kind_with_a_company_block_prints_it_and_the_preflight_checks_its_data(): void {
        foreach (RenderDocumentKind::cases() as $kind) {
            $this->assertSame(in_array(InformationBlock::CompanyIdentity, $kind->mandatoryBlocks(), true), $kind->printsIssuerBlocks(), $kind->value);
        }

        $service = app(RenderProfileService::class);
        $bare = Organization::factory()->create();
        $bareAdmin = User::factory()->admin()->create(['organization_id' => $bare->id]);
        app()->instance('currentOrganization', $bare);
        foreach ([RenderDocumentKind::ManufacturingRecord, RenderDocumentKind::Certificate, RenderDocumentKind::SigningCertificate, RenderDocumentKind::ConstructionObstructionNotice, RenderDocumentKind::ConstructionConcernNotice] as $kind) {
            $profile = $service->createProfile($bare, 'Profil ' . $kind->value, [$kind->value], false, $bareAdmin);
            $result = app(RenderPreflightService::class)->check($profile->versions()->firstOrFail());
            $this->assertContains('company_identity', array_column(array_filter($result->errors, fn (array $e): bool => $e['code'] === 'mandatory_data_missing'), 'block'), $kind->value);
        }
    }

    public function test_construction_notice_prints_the_sender_and_letters_sent_before_stay_unchanged(): void {
        $service = app(ConstructionNoticeService::class);
        $notice = $service->create($this->org, RenderDocumentKind::ConstructionObstructionNotice, ['subject' => 'Fehlende Vorleistung', 'occurred_on' => '2026-11-02', 'facts' => 'Deckenöffnungen fehlen.'], $this->admin);
        $this->assertPrintsIssuer('pdf.construction-notice', fn () => app(ConstructionNoticePdfRenderer::class)->render($notice));

        // Der erste Versand friert die Gestaltung mit ein — das Schreiben bleibt, wie es zuging.
        $service->markSent($notice);
        $this->assertSame(DocumentDesignRenderer::GENERATOR_VERSION, $this->snapshotVersion($notice));
        $this->assertPrintsIssuer('pdf.construction-notice', fn () => app(ConstructionNoticePdfRenderer::class)->render($notice->refresh()));

        $legacy = $service->create($this->org, RenderDocumentKind::ConstructionConcernNotice, ['subject' => 'Bedenken Abdichtung', 'occurred_on' => '2026-10-01', 'facts' => 'Ungeeigneter Untergrund.'], $this->admin);
        $legacy->forceFill(['status' => \App\Enums\Construction\ConstructionNoticeStatus::Sent, 'sent_at' => '2026-10-02 09:00:00'])->save();
        $this->assertPrintsIssuer('pdf.construction-notice', fn () => app(ConstructionNoticePdfRenderer::class)->render($legacy->refresh()), false);
    }

    public function test_safety_evidence_prints_the_employer_and_signed_or_approved_records_stay_unchanged(): void {
        $signer = User::factory()->aussendienst()->create(['organization_id' => $this->org->id]);
        $service = app(SafetyInstructionService::class);
        $instruction = $service->create($this->org, $this->admin, ['topic' => 'Leitern und Tritte', 'held_on' => now()->toDateString()], [$signer->id]);
        $this->assertPrintsIssuer('pdf.safety-instruction', fn () => app(SafetyEvidencePdfRenderer::class)->instruction($instruction));

        $service->sign($instruction->participants()->firstOrFail(), $signer);
        $this->assertSame(DocumentDesignRenderer::GENERATOR_VERSION, $this->snapshotVersion($instruction));
        $this->assertPrintsIssuer('pdf.safety-instruction', fn () => app(SafetyEvidencePdfRenderer::class)->instruction($instruction->refresh()));

        $draft = HazardAssessment::factory()->create(['organization_id' => $this->org->id]);
        $this->assertPrintsIssuer('pdf.safety-assessment', fn () => app(SafetyEvidencePdfRenderer::class)->assessment($draft));
        // Vor dieser Änderung freigegeben: ohne Snapshot bleibt die Beurteilung, wie sie war.
        DB::table('hazard_assessments')->where('id', $draft->id)->update(['status' => 'approved', 'approved_at' => '2026-10-01 10:00:00']);
        $this->assertPrintsIssuer('pdf.safety-assessment', fn () => app(SafetyEvidencePdfRenderer::class)->assessment($draft->refresh()), false);
    }

    public function test_manufacturing_record_prints_the_issuer_and_older_completed_records_stay_unchanged(): void {
        $order = ManufacturingOrder::factory()->create(['organization_id' => $this->org->id, 'number' => 'FA-1', 'status' => ManufacturingOrderStatus::InProgress->value]);
        $this->assertPrintsIssuer('pdf.manufacturing-record', fn () => app(ManufacturingRecordPdfRenderer::class)->render($order));

        DB::table('manufacturing_orders')->where('id', $order->id)->update(['status' => ManufacturingOrderStatus::Completed->value]);
        $this->assertPrintsIssuer('pdf.manufacturing-record', fn () => app(ManufacturingRecordPdfRenderer::class)->render($order->refresh()), false);
    }

    public function test_certificates_name_the_issuer_from_now_on(): void {
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->org->id]);
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->org, null, ['title' => 'Brandschutz kompakt']);
        $courses->addUnit($course, ['title' => 'Teil 1', 'points' => 5]);
        $courses->release($course->refresh(), null);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);
        $certificate = app(LearningCompletionService::class)->issueCertificate($enrollment);

        $this->assertSame(DocumentDesignRenderer::GENERATOR_VERSION, $this->snapshotVersion($certificate));
        $this->assertPrintsIssuer('learning.pdf.certificate', fn () => app(LearningCertificatePdfRenderer::class)->output($certificate));
        $this->assertPrintsIssuer('learning.pdf.report-card', fn () => app(LearningReportCardPdfRenderer::class)->output($enrollment));
        // Vorher ausgestellt (kein Snapshot): der Nachdruck bleibt ohne Firmenangaben.
        DocumentRenderSnapshot::query()->withoutGlobalScopes()->where('documentable_id', $certificate->id)->where('documentable_type', $certificate->getMorphClass())->delete();
        $this->assertPrintsIssuer('learning.pdf.certificate', fn () => app(LearningCertificatePdfRenderer::class)->output($certificate), false);

        $grading = app(ClubGradingService::class);
        $grading->setEnabled($this->org, true);
        $system = $grading->createSystem($this->org, ['name' => 'Judo-Ordnung', 'discipline' => 'Judo']);
        $grade = $grading->createGrade($system, ['name' => 'Weiß', 'rank' => 0]);
        $awarded = $grading->recognizeGrade(ClubMember::factory()->aged(20)->create(['joined_on' => '2026-01-01']), $grade, CarbonImmutable::parse('2026-03-01'), null, $this->admin);
        $this->assertSame(DocumentDesignRenderer::GENERATOR_VERSION, $this->snapshotVersion($awarded));
        $this->assertPrintsIssuer('club.pdf.grade_certificate', fn () => app(ClubGradeCertificatePdfRenderer::class)->output($awarded));
    }

    private function assertPrintsIssuer(string $view, \Closure $render, bool $expected = true): void {
        unset($this->captured[$view]);
        $render();
        $html = view($view, $this->captured[$view])->render();

        if ($expected) {
            $this->assertStringContainsString('data-block="company_identity"', $html, $view);
            $this->assertStringContainsString('Musterstraße 1', $html, $view);
        } else {
            $this->assertStringNotContainsString('data-block="company_identity"', $html, $view);
        }
    }

    private function snapshotVersion(Model $document): ?string {
        return DocumentRenderSnapshot::query()->withoutGlobalScopes()
            ->where('documentable_type', $document->getMorphClass())
            ->where('documentable_id', $document->getKey())
            ->value('generator_version');
    }
}
