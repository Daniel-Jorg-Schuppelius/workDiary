<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StationeryOverlayRegressionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\{LetterheadPageRole, RenderDocumentKind};
use App\Models\DocumentDesign\LetterheadAsset;
use App\Models\{Organization, User};
use App\Services\DocumentDesign\{DocumentDesignRenderer, LetterheadAssetService, RenderProfileService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage, View};
use TCPDF;
use Tests\TestCase;

/**
 * Regressions-Gate zum Umstieg auf den Vektor-Overlay (Audit 2026-08, W5.5;
 * Nutzer-Entscheid E4).
 *
 * Der Vergleich ist bewusst inhaltlich, nicht pixelweise: Ein PDF-Diff über
 * Bytes wäre nach dem Umstieg IMMER rot (andere Objektstruktur) und würde
 * genau nichts über die Ausgabe aussagen. Geprüft wird deshalb:
 *
 *  1. Der Textinhalt des Dokuments bleibt identisch — der Umstieg darf am
 *     Inhalt nichts ändern, nur an der Hintergrund-Ebene.
 *  2. Der Bogen ist als VEKTOR drin (sein Text ist extrahierbar), nicht als
 *     Rasterbild — das ist der ganze Zweck der Umstellung.
 *  3. Der Bild-Weg bleibt für Raster-Bögen erhalten (kein halber Umstieg).
 *  4. Ein kaputter Bogen kostet den Hintergrund, nicht das Dokument.
 */
class StationeryOverlayRegressionTest extends TestCase {
    use RefreshDatabase;

    private static ?string $pdftotext = null;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        // Minimales Dokument-HTML nur für diesen Test — keine Produktions-View
        // dafür anlegen.
        View::addLocation(base_path('tests/Fixtures/views'));
    }

    public function test_vector_overlay_keeps_the_document_text_and_adds_the_letterhead(): void {
        [$org, $admin] = $this->orgAdmin();
        $asset = $this->uploadPdfLetterhead($org, $admin, 'MUSTERFIRMA-BOGEN');
        $this->activateProfile($org, $admin, $asset);

        $pdf = app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Invoice,
            'plain-document',
            ['text' => 'RECHNUNGSINHALT-XYZ'],
            $org,
        );

        $text = $this->textOf($pdf);
        $this->assertStringContainsString('RECHNUNGSINHALT-XYZ', $text, 'Der Dokumentinhalt darf sich nicht ändern.');
        $this->assertStringContainsString('MUSTERFIRMA-BOGEN', $text, 'Der Bogen liegt als Vektor bei — sein Text ist extrahierbar.');
    }

    /** Ohne Bogen bleibt die Ausgabe genau die bisherige. */
    public function test_without_letterhead_the_output_is_unchanged(): void {
        [$org, $admin] = $this->orgAdmin();
        $this->activateProfile($org, $admin, null);

        $pdf = app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Invoice,
            'plain-document',
            ['text' => 'OHNE-BOGEN'],
            $org,
        );

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('OHNE-BOGEN', $this->textOf($pdf));
    }

    /** Raster-Bogen (PNG-Upload): der alte Bild-Weg bleibt, kein halber Umstieg. */
    public function test_raster_letterhead_still_uses_the_image_path(): void {
        [$org, $admin] = $this->orgAdmin();
        $asset = $this->uploadRasterLetterhead($org, $admin);
        $this->assertFalse($asset->hasVectorSource());

        $payload = app(DocumentDesignRenderer::class)->payloadFor($org, RenderDocumentKind::Invoice);
        $this->assertNotNull($payload);

        $html = app(DocumentDesignRenderer::class)->compose('<html><head></head><body>X</body></html>', $payload);
        $this->assertStringContainsString('dd-lh-first', $html, 'Rasterbögen kommen weiterhin als <img> ins HTML.');
    }

    /** Beim Vektor-Weg verschwindet das Hintergrundbild aus dem HTML. */
    public function test_vector_mode_drops_the_background_image_from_the_html(): void {
        [$org, $admin] = $this->orgAdmin();
        $asset = $this->uploadPdfLetterhead($org, $admin, 'BOGEN');
        $this->activateProfile($org, $admin, $asset);

        $payload = app(DocumentDesignRenderer::class)->payloadFor($org, RenderDocumentKind::Invoice);
        $renderer = app(DocumentDesignRenderer::class);

        $withImage = $renderer->compose('<html><head></head><body>X</body></html>', $payload);
        $vector = $renderer->compose('<html><head></head><body>X</body></html>', $payload, vectorOverlay: true);

        $this->assertStringContainsString('dd-lh-first', $withImage);
        $this->assertStringNotContainsString('dd-lh-first', $vector);
        // Die Ränder bleiben in BEIDEN Fassungen gleich — der Umstieg darf das
        // Layout des Inhalts nicht verschieben.
        $this->assertStringContainsString('@page {', $vector);
        $this->assertSame(
            $this->pageRule($withImage),
            $this->pageRule($vector),
            'Der Druckbereich muss unverändert bleiben.',
        );
    }

    /** Ein unbrauchbarer Bogen kostet den Hintergrund, nicht das Dokument. */
    public function test_broken_letterhead_still_yields_a_document(): void {
        [$org, $admin] = $this->orgAdmin();
        $asset = $this->uploadPdfLetterhead($org, $admin, 'BOGEN');
        $this->activateProfile($org, $admin, $asset);

        // Original zerstören: der Overlay muss scheitern, das Rendern nicht.
        Storage::disk($asset->disk)->put($asset->original_path, 'kein pdf');

        $pdf = app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Invoice,
            'plain-document',
            ['text' => 'TROTZDEM-DA'],
            $org,
        );

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('TROTZDEM-DA', $this->textOf($pdf));
    }

    // ── Hilfen ──────────────────────────────────────────────────────────

    /** @return array{0: Organization, 1: User} */
    private function orgAdmin(): array {
        $org = Organization::factory()->create();
        $this->app->instance('currentOrganization', $org);

        return [$org, User::factory()->admin()->create(['organization_id' => $org->id])];
    }

    private function uploadPdfLetterhead(Organization $org, User $admin, string $marker): LetterheadAsset {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, $marker);

        $file = UploadedFile::fake()->createWithContent('bogen.pdf', $pdf->Output('', 'S'));

        return app(LetterheadAssetService::class)->store($org, $file, LetterheadPageRole::First, 'Bogen', $admin);
    }

    private function uploadRasterLetterhead(Organization $org, User $admin): LetterheadAsset {
        $img = imagecreatetruecolor(630, 891);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 230, 240, 250));
        ob_start();
        imagepng($img);
        imagedestroy($img);
        $file = UploadedFile::fake()->createWithContent('bogen.png', (string) ob_get_clean());

        $asset = app(LetterheadAssetService::class)->store($org, $file, LetterheadPageRole::First, 'Bogen', $admin);
        $this->activateProfile($org, $admin, $asset);

        return $asset->fresh();
    }

    private function activateProfile(Organization $org, User $admin, ?LetterheadAsset $asset): void {
        $service = app(RenderProfileService::class);
        $profile = $service->createProfile($org, 'Design ' . uniqid(), ['invoice'], true, $admin);
        $version = $profile->versions()->firstOrFail();

        $layout = $version->layout;
        $layout['content_first'] = ['top' => 45, 'right' => 20, 'bottom' => 25, 'left' => 20];
        $layout['content_following'] = ['top' => 25, 'right' => 20, 'bottom' => 25, 'left' => 20];
        $service->updateDraft($version, array_filter([
            'layout' => $layout,
            'first_asset_id' => $asset?->id,
        ], static fn ($v): bool => $v !== null), $admin);

        $this->assertTrue($service->activate($version->fresh(), $admin)->ok());
    }

    private function pageRule(string $html): string {
        preg_match('/@page \{[^}]*\}/', $html, $m);

        return $m[0] ?? '';
    }

    private function textOf(string $pdfBytes): string {
        if (self::$pdftotext === null) {
            self::$pdftotext = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        }
        if (self::$pdftotext === '') {
            $this->markTestSkipped('pdftotext ist nicht installiert.');
        }

        $path = tempnam(sys_get_temp_dir(), 'dd_') . '.pdf';
        file_put_contents($path, $pdfBytes);
        try {
            return (string) shell_exec(escapeshellarg(self::$pdftotext) . ' ' . escapeshellarg($path) . ' -');
        } finally {
            @unlink($path);
        }
    }
}
