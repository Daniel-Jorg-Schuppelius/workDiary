<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RenderPipelineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\{LetterheadPageRole, RenderDocumentKind};
use App\Models\{Customer, Invoice, Organization, User};
use App\Models\DocumentDesign\DocumentRenderSnapshot;
use App\Services\DocumentDesign\{DocumentDesignRenderer, LetterheadAssetService, RenderProfileService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MVP-300/301/304: Komposition (Firmenbogen, Druckbereiche, Tabellenstil),
 * Systemfallback und unveränderlicher Snapshot beim Ausstellen.
 */
class RenderPipelineTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeOrgAdmin(): array {
        $org = Organization::factory()->create();
        $this->app->instance('currentOrganization', $org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        return [$org, $admin];
    }

    private function makeInvoice(Organization $org, Customer $customer): Invoice {
        return Invoice::create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'number' => 'R-' . uniqid(),
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
        ]);
    }

    private function uploadAsset(Organization $org, User $admin): \App\Models\DocumentDesign\LetterheadAsset {
        $img = imagecreatetruecolor(630, 891);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 230, 240, 250));
        ob_start();
        imagepng($img);
        imagedestroy($img);
        $file = UploadedFile::fake()->createWithContent('bogen.png', (string) ob_get_clean());

        return app(LetterheadAssetService::class)->store($org, $file, LetterheadPageRole::First, 'Bogen', $admin);
    }

    /** @return array{0: Organization, 1: User, 2: \App\Models\DocumentDesign\DocumentRenderProfile} */
    private function makeActiveProfile(array $kinds = ['invoice']): array {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = app(RenderProfileService::class);
        $profile = $service->createProfile($org, 'Design', $kinds, true, $admin);
        $version = $profile->versions()->firstOrFail();

        $asset = $this->uploadAsset($org, $admin);
        $layout = $version->layout;
        $layout['content_first'] = ['top' => 45, 'right' => 20, 'bottom' => 25, 'left' => 20];
        $layout['content_following'] = ['top' => 25, 'right' => 20, 'bottom' => 25, 'left' => 20];
        $layout['footer'] = ['page_numbers' => true, 'carryover_note' => false];
        $service->updateDraft($version, ['layout' => $layout, 'first_asset_id' => $asset->id], $admin);
        $this->assertTrue($service->activate($version->fresh(), $admin)->ok());

        return [$org, $admin, $profile->fresh()];
    }

    public function test_compose_injects_margins_letterhead_and_page_numbers(): void {
        [$org] = $this->makeActiveProfile();
        $renderer = app(DocumentDesignRenderer::class);

        $html = '<html><head><style>@page { margin: 20mm; }</style></head><body><p>Inhalt</p></body></html>';
        $composed = $renderer->compose($html, $renderer->payloadFor($org, RenderDocumentKind::Invoice));

        // @page-Ränder aus dem Profil (Folgeseiten) nach den View-Styles → gewinnen.
        $this->assertStringContainsString('@page { margin: 25.0mm 20.0mm 25.0mm 20.0mm; }', $composed);
        // Firmenbogen als eingebetteter, nicht nachladbarer Data-URI.
        $this->assertStringContainsString('class="dd-lh-first" src="data:image/png;base64,', $composed);
        // Erste Seite beginnt 20 mm tiefer als Folgeseiten → Abstandshalter.
        $this->assertStringContainsString('dd-first-offset" style="height: 20.0mm"', $composed);
        // Seitenzahl über CSS-Counter.
        $this->assertStringContainsString('counter(page)', $composed);
    }

    public function test_compose_without_profile_is_a_noop(): void {
        [$org] = $this->makeOrgAdmin();
        $renderer = app(DocumentDesignRenderer::class);

        $html = '<html><head></head><body><p>Inhalt</p></body></html>';
        $this->assertSame($html, $renderer->compose($html, $renderer->payloadFor($org, RenderDocumentKind::Invoice)));
    }

    public function test_invoice_issue_freezes_render_snapshot(): void {
        [$org, $admin, $profile] = $this->makeActiveProfile(['invoice']);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $invoice = $this->makeInvoice($org, $customer);

        $invoice->freezeParties();
        $invoice->save();

        $snapshot = DocumentRenderSnapshot::query()
            ->where('documentable_id', $invoice->id)
            ->firstOrFail();
        $this->assertSame($profile->active_version_id, $snapshot->profile_version_id);
        $this->assertNotNull($snapshot->first_asset_sha256);
        $this->assertSame(DocumentDesignRenderer::GENERATOR_VERSION, $snapshot->generator_version);

        // Snapshots sind unveränderlich.
        $this->expectException(\RuntimeException::class);
        $snapshot->payload = ['manipuliert' => true];
        $snapshot->save();
    }

    public function test_finalized_invoice_renders_with_frozen_profile_despite_later_changes(): void {
        [$org, $admin, $profile] = $this->makeActiveProfile(['invoice']);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $invoice = $this->makeInvoice($org, $customer);
        $invoice->freezeParties();
        $invoice->save();

        $frozenVersionId = $profile->active_version_id;

        // Später: neue Profilversion mit anderem Layout aktivieren.
        $service = app(RenderProfileService::class);
        $v2 = $service->newDraftFrom($profile->activeVersion()->firstOrFail(), $admin);
        $layout = $v2->layout;
        $layout['content_first']['top'] = 90;
        $service->updateDraft($v2, ['layout' => $layout], $admin);
        $service->activate($v2->fresh(), $admin);

        $renderer = app(DocumentDesignRenderer::class);
        $payload = $renderer->payloadFromSnapshot($invoice, RenderDocumentKind::Invoice);
        $this->assertNotNull($payload);
        $this->assertSame($frozenVersionId, $payload['profile_version_id']);
        $this->assertSame(45, (int) $payload['layout']['content_first']['top']);
    }

    public function test_snapshot_without_profile_records_system_fallback(): void {
        [$org] = $this->makeOrgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $invoice = $this->makeInvoice($org, $customer);

        $invoice->freezeParties();
        $invoice->save();

        $snapshot = DocumentRenderSnapshot::query()->where('documentable_id', $invoice->id)->firstOrFail();
        $this->assertNull($snapshot->profile_version_id);
        $this->assertSame('system_default', $snapshot->payload['fallback'] ?? null);

        // Fallback-Snapshot rendert wie heute (kein Design-Payload).
        $renderer = app(DocumentDesignRenderer::class);
        $this->assertNull($renderer->payloadFromSnapshot($invoice, RenderDocumentKind::Invoice));
    }

    public function test_test_pdf_download_works_for_admin(): void {
        [$org, $admin, $profile] = $this->makeActiveProfile(['invoice']);

        $response = $this->actingAs($admin)->get(
            route('admin.document-design.test-pdf', ['profile' => $profile->sqid, 'kind' => 'invoice']),
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }
}
