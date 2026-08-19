<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LandscapeDesignTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\{LetterheadPageRole, PageFormat, RenderDocumentKind};
use App\Models\DocumentDesign\LetterheadAsset;
use App\Models\{Organization, User};
use App\Services\DocumentDesign\{DocumentDesignRenderer, LetterheadAssetService, RenderProfileService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * MVP-652 (Issue #85): Querformat-Design. Profile und Firmenbögen tragen ihr
 * Seitenformat; Querformat-Berichte laufen nicht mehr design-frei, sondern
 * gegen ein A4-quer-Profil. Hochformat bleibt unverändert.
 */
class LandscapeDesignTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        // Render-Testseite als Fixture — keine Test-View im Produktivcode.
        \Illuminate\Support\Facades\View::addNamespace('dd-test', base_path('tests/Fixtures/views'));
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    private function service(): RenderProfileService {
        return app(RenderProfileService::class);
    }

    /** Aktives Profil im gewünschten Seitenformat. */
    private function activeProfile(PageFormat $format, string $name): void {
        $profile = $this->service()->createProfile($this->org, $name, [], true, $this->admin, null, $format);
        $this->assertTrue($this->service()->activate($profile->versions()->firstOrFail(), $this->admin)->ok());
    }

    /** Firmenbogen-PNG im Seitenverhältnis des Formats. */
    private function uploadAsset(PageFormat $format): LetterheadAsset {
        $width = 630;
        $height = (int) round($width * $format->aspectRatio());
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 240, 245, 250));
        ob_start();
        imagepng($img);
        imagedestroy($img);
        $file = UploadedFile::fake()->createWithContent('bogen.png', (string) ob_get_clean());

        return app(LetterheadAssetService::class)->store($this->org, $file, LetterheadPageRole::First, 'Bogen', $this->admin, $format);
    }

    public function test_landscape_report_renders_against_landscape_profile(): void {
        $this->activeProfile(PageFormat::A4Landscape, 'Querformat-Berichte');
        $renderer = app(DocumentDesignRenderer::class);

        // Hochformat-Auflösung findet KEIN Profil (nur ein Querformat-Profil aktiv).
        $this->assertNull($renderer->payloadFor($this->org, RenderDocumentKind::Report));
        $landscape = $renderer->payloadFor($this->org, RenderDocumentKind::Report, null, PageFormat::A4Landscape);
        $this->assertNotNull($landscape, 'Querformat-Profil wird für Querformat aufgelöst.');

        $composed = $renderer->compose('<html><head></head><body></body></html>', $landscape, PageFormat::A4Landscape);
        $this->assertStringContainsString('size: 297mm 210mm;', $composed, 'Querformat setzt die Seitengröße explizit.');
    }

    public function test_portrait_output_is_unchanged(): void {
        $this->activeProfile(PageFormat::A4Portrait, 'CI-Basisdesign');
        $renderer = app(DocumentDesignRenderer::class);

        $composed = $renderer->compose(
            '<html><head></head><body></body></html>',
            $renderer->payloadFor($this->org, RenderDocumentKind::Report),
        );
        $this->assertStringContainsString('@page { margin:', $composed, 'Hochformat bleibt ohne size-Regel (Bestandsausgabe).');
        $this->assertStringNotContainsString('@page { size:', $composed);
    }

    public function test_render_pdf_composes_landscape_and_skips_special_formats(): void {
        $this->activeProfile(PageFormat::A4Landscape, 'Querformat-Berichte');
        $renderer = app(DocumentDesignRenderer::class);

        // Querformat-Bericht: PDF entsteht über die Design-Pipeline.
        $pdf = $renderer->renderPdf(
            RenderDocumentKind::Report,
            'dd-test::blank-test',
            [],
            $this->org,
            ['paper_size' => 'a4', 'orientation' => 'landscape'],
        );
        $this->assertStringStartsWith('%PDF', $pdf);

        // Etikett bleibt deklariertes Spezialformat (kein Design).
        $this->assertFalse(RenderDocumentKind::Label->isBrandable());
        $this->assertNull(RenderDocumentKind::Label->pageFormat());
        $this->assertTrue(RenderDocumentKind::Report->supportsLandscape());
        $this->assertFalse(RenderDocumentKind::Invoice->supportsLandscape());
    }

    public function test_letterhead_upload_validates_aspect_per_format(): void {
        $landscapeAsset = $this->uploadAsset(PageFormat::A4Landscape);
        $this->assertTrue($landscapeAsset->isReady());
        $this->assertSame(PageFormat::A4Landscape, $landscapeAsset->page_format);
        $this->assertSame('297.00', $landscapeAsset->width_mm);

        // Hochformat-Bogen mit Querformat-Seitenverhältnis → Prüfung erforderlich.
        $img = imagecreatetruecolor(900, 640);
        ob_start();
        imagepng($img);
        imagedestroy($img);
        $file = UploadedFile::fake()->createWithContent('quer.png', (string) ob_get_clean());
        $mismatch = app(LetterheadAssetService::class)->store($this->org, $file, LetterheadPageRole::First, 'Falsch', $this->admin, PageFormat::A4Portrait);
        $this->assertFalse($mismatch->isReady());
        $this->assertNotEmpty($mismatch->review_notes);
    }

    public function test_preflight_rejects_letterhead_with_foreign_page_format(): void {
        $service = $this->service();
        $portraitAsset = $this->uploadAsset(PageFormat::A4Portrait);

        $profile = $service->createProfile($this->org, 'Querformat', [], false, $this->admin, null, PageFormat::A4Landscape);
        $version = $profile->versions()->firstOrFail();
        $service->updateDraft($version, ['first_asset_id' => $portraitAsset->id], $this->admin);

        $result = $service->activate($version->refresh(), $this->admin);
        $this->assertFalse($result->ok());
        $this->assertSame('asset_format_mismatch', $result->errors[0]['code']);
    }

    public function test_landscape_geometry_uses_wide_page_bounds(): void {
        $service = $this->service();
        $profile = $service->createProfile($this->org, 'Querformat', [], false, $this->admin, null, PageFormat::A4Landscape);
        $version = $profile->versions()->firstOrFail();

        // 250 mm breites Adressfenster: im Hochformat außerhalb der Seite, im Querformat gültig.
        $layout = $version->layout;
        $layout['address_window'] = ['x' => 20, 'y' => 40, 'width' => 250, 'height' => 30];
        $service->updateDraft($version, ['layout' => $layout], $this->admin);
        $this->assertTrue($service->activate($version->refresh(), $this->admin)->ok(), 'Querformat erlaubt die breite Box.');

        $portrait = $service->createProfile($this->org, 'Hochformat', [], false, $this->admin);
        $portraitVersion = $portrait->versions()->firstOrFail();
        $service->updateDraft($portraitVersion, ['layout' => $layout], $this->admin);
        $portraitResult = $service->activate($portraitVersion->refresh(), $this->admin);
        $this->assertFalse($portraitResult->ok(), 'Dieselbe Box sprengt das Hochformat.');
        $this->assertSame('box_off_page', $portraitResult->errors[0]['code']);
    }

    public function test_page_format_is_locked_after_activation(): void {
        $service = $this->service();
        $profile = $service->createProfile($this->org, 'P', [], false, $this->admin);
        $service->assignPageFormat($profile, PageFormat::A4Landscape);
        $this->assertSame(PageFormat::A4Landscape, $profile->refresh()->page_format);

        $this->assertTrue($service->activate($profile->versions()->firstOrFail(), $this->admin)->ok());
        $this->expectException(RuntimeException::class);
        $service->assignPageFormat($profile->refresh(), PageFormat::A4Portrait);
    }
}
