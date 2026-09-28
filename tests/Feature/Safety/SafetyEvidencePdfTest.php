<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEvidencePdfTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Safety;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Safety\HazardAssessmentStatus;
use App\Models\Platform\User;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Safety\{HazardAssessmentService, SafetyEvidencePdfRenderer, SafetyInstructionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-985: Unterweisungsnachweis und Gefährdungsbeurteilung als PDF. */
final class SafetyEvidencePdfTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_instruction_pdf_carries_participants_and_drawn_signature(): void {
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id, 'name' => 'Lea Leitung']);
        $signer = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id, 'name' => 'Sam Signer']);
        $open = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id, 'name' => 'Otto Offen']);
        $service = app(SafetyInstructionService::class);
        $instruction = $service->create($this->organization, $lead, ['topic' => 'Leitern und Tritte', 'held_on' => now()->toDateString()], [$signer->id, $open->id]);
        $participant = $instruction->participants()->where('user_id', $signer->id)->firstOrFail();
        $service->signDrawn($participant, $signer, self::PNG, '10.0.0.5');

        $captured = null;
        $design = Mockery::mock(DocumentDesignRenderer::class);
        $design->shouldReceive('renderPdf')->once()
            ->withArgs(function (RenderDocumentKind $kind, string $view, array $data) use (&$captured): bool {
                $captured = $data;

                return $kind === RenderDocumentKind::Protocol && $view === 'pdf.safety-instruction';
            })
            ->andReturn('%PDF-test');
        $this->app->instance(DocumentDesignRenderer::class, $design);

        $this->assertSame('%PDF-test', app(SafetyEvidencePdfRenderer::class)->instruction($instruction));
        $this->assertStringStartsWith('data:image/png;base64,', (string) $captured['signatures'][$participant->id]);
        $html = view('pdf.safety-instruction', $captured)->render();
        $this->assertStringContainsString('Sam Signer', $html);
        $this->assertStringContainsString('Otto Offen', $html);
        $this->assertStringContainsString(__('safety.register.pdf.open'), $html);
        $this->assertStringContainsString(substr((string) $participant->refresh()->hash, 0, 16), $html);
    }

    public function test_pdf_downloads_are_gated_and_deliver_a_pdf(): void {
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $participantUser = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $instruction = app(SafetyInstructionService::class)->create($this->organization, $lead, ['topic' => 'Brandschutz', 'held_on' => now()->toDateString()], [$participantUser->id]);

        $assessments = app(HazardAssessmentService::class);
        $assessment = $assessments->create($this->organization, $lead, ['area' => 'Werkstatt']);
        $assessments->addItem($assessment, ['hazard' => 'Rotierende Teile', 'measure' => 'Schutzhaube', 'severity_before' => 4, 'likelihood_before' => 3, 'severity_after' => 2, 'likelihood_after' => 1]);
        $assessments->transition($assessment, HazardAssessmentStatus::InReview, $lead);

        // Die Teilnehmerin sieht ihren Nachweis, aber nicht das PDF mit allen Unterschriften.
        $this->actingAs($participantUser)->get(route('safety.instructions.show', $instruction))->assertOk()->assertDontSee(route('safety.instructions.pdf', $instruction));
        $this->actingAs($participantUser)->get(route('safety.instructions.pdf', $instruction))->assertForbidden();
        $this->actingAs($participantUser)->get(route('safety.assessments.pdf', $assessment))->assertForbidden();

        $this->actingAs($lead)->get(route('safety.instructions.show', $instruction))->assertOk()->assertSee(route('safety.instructions.pdf', $instruction));
        $response = $this->actingAs($lead)->get(route('safety.instructions.pdf', $instruction));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertStringContainsString('Unterweisungsnachweis_UW-', (string) $response->headers->get('Content-Disposition'));

        $response = $this->actingAs($lead)->get(route('safety.assessments.pdf', $assessment));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }
}
