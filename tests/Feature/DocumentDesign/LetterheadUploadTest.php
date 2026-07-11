<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LetterheadUploadTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\LetterheadAssetStatus;
use App\Models\DocumentDesign\LetterheadAsset;
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MVP-296/304: sicherer Firmenbogen-Upload — Signaturprüfung, Normalisierung
 * zu deckendem PNG, Prüfstatus und Mandantenisolation.
 */
class LetterheadUploadTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeOrg(): Organization {
        $org = Organization::factory()->create();
        $this->app->instance('currentOrganization', $org);

        return $org;
    }

    /** A4-Hochformat-PNG (Seitenverhältnis 210:297) als Upload. */
    private function a4Png(int $width = 630): UploadedFile {
        $height = (int) round($width * 297 / 210);
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 240, 240, 255));
        ob_start();
        imagepng($img);
        imagedestroy($img);
        $bytes = (string) ob_get_clean();

        return UploadedFile::fake()->createWithContent('bogen.png', $bytes);
    }

    public function test_admin_can_upload_png_letterhead_and_it_normalizes(): void {
        $org = $this->makeOrg();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($admin)->post(route('admin.document-design.assets.store'), [
            'name' => 'Bogen Seite 1',
            'page_role' => 'first',
            'file' => $this->a4Png(),
        ]);

        $response->assertRedirect(route('admin.document-design.index'));
        $asset = LetterheadAsset::query()->firstOrFail();
        $this->assertSame(LetterheadAssetStatus::Ready, $asset->status);
        $this->assertNotNull($asset->normalized_path);
        $this->assertNotNull($asset->normalized_sha256);
        $this->assertTrue(Storage::disk('local')->exists($asset->original_path));
        $this->assertTrue(Storage::disk('local')->exists($asset->normalized_path));
        // Normalisiert = PNG-Signatur, unabhängig vom Eingangsformat.
        $this->assertStringStartsWith("\x89PNG", (string) Storage::disk('local')->get($asset->normalized_path));
    }

    public function test_signature_mismatch_is_rejected(): void {
        $org = $this->makeOrg();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        // Deklariert als PDF, tatsächlich Text → Endung/Inhalt-Mismatch.
        $file = UploadedFile::fake()->createWithContent('fake.pdf', 'kein pdf inhalt');

        $response = $this->actingAs($admin)->from(route('admin.document-design.index'))
            ->post(route('admin.document-design.assets.store'), [
                'name' => 'Böse Datei',
                'page_role' => 'first',
                'file' => $file,
            ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, LetterheadAsset::query()->count());
    }

    public function test_wrong_aspect_ratio_requires_review_and_cannot_be_used(): void {
        $org = $this->makeOrg();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $img = imagecreatetruecolor(800, 600); // Querformat
        ob_start();
        imagepng($img);
        imagedestroy($img);
        $file = UploadedFile::fake()->createWithContent('quer.png', (string) ob_get_clean());

        $this->actingAs($admin)->post(route('admin.document-design.assets.store'), [
            'name' => 'Querformat',
            'page_role' => 'first',
            'file' => $file,
        ]);

        $asset = LetterheadAsset::query()->firstOrFail();
        $this->assertSame(LetterheadAssetStatus::ReviewRequired, $asset->status);
        $this->assertFalse($asset->isReady());
        $this->assertNotEmpty($asset->review_notes);
    }

    public function test_pdf_letterhead_is_rasterized_to_non_interactive_png(): void {
        if (! is_file('/usr/bin/pdftoppm') && ! is_file('/usr/local/bin/pdftoppm')) {
            $this->markTestSkipped('pdftoppm nicht verfügbar.');
        }

        $org = $this->makeOrg();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        // Minimales einseitiges A4-PDF über die vorhandene PDF-Pipeline erzeugen.
        $pdf = \PDFToolkit\Registries\PDFWriterRegistry::getInstance()->createPdfString(
            \PDFToolkit\Entities\PDFContent::fromHtml('<html><head><style>@page { margin: 0; size: A4 portrait; }</style></head><body><p>Briefkopf</p></body></html>'),
        );
        $this->assertNotNull($pdf);
        $file = UploadedFile::fake()->createWithContent('briefbogen.pdf', $pdf);

        $this->actingAs($admin)->post(route('admin.document-design.assets.store'), [
            'name' => 'PDF-Bogen',
            'page_role' => 'first',
            'file' => $file,
        ]);

        $asset = LetterheadAsset::query()->firstOrFail();
        $this->assertSame(LetterheadAssetStatus::Ready, $asset->status, implode(' ', $asset->review_notes ?? []));
        $this->assertStringStartsWith("\x89PNG", (string) Storage::disk('local')->get((string) $asset->normalized_path));
    }

    public function test_plain_user_cannot_upload_or_view_assets(): void {
        $org = $this->makeOrg();
        $user = User::factory()->user()->create(['organization_id' => $org->id]);

        $this->actingAs($user)->post(route('admin.document-design.assets.store'), [
            'name' => 'X',
            'page_role' => 'first',
            'file' => $this->a4Png(),
        ])->assertForbidden();

        $this->actingAs($user)->get(route('admin.document-design.index'))->assertForbidden();
    }

    public function test_asset_preview_is_tenant_isolated(): void {
        $org = $this->makeOrg();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $this->actingAs($admin)->post(route('admin.document-design.assets.store'), [
            'name' => 'Bogen',
            'page_role' => 'first',
            'file' => $this->a4Png(),
        ]);
        $asset = LetterheadAsset::query()->firstOrFail();

        // Admin einer FREMDEN Organisation darf die Vorschau nicht sehen.
        $foreignOrg = Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $foreignOrg->id]);
        $this->app->instance('currentOrganization', $foreignOrg);

        $this->actingAs($foreignAdmin)
            ->get(route('admin.document-design.assets.preview', $asset->sqid))
            ->assertNotFound();
    }
}
