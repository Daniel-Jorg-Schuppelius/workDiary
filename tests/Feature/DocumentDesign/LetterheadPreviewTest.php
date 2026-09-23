<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LetterheadPreviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\{LetterheadAssetStatus, LetterheadPageRole};
use App\Models\Document\DocumentDesign\{DocumentRenderProfileVersion, LetterheadAsset};
use App\Models\Platform\{Organization, User};
use App\Services\DocumentDesign\RenderProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nachschnitt 6 (Feature 076): Firmenbögen sind auch ohne Rasterseite
 * einsehbar (Dialog + Original-Download), die Übersicht führt per
 * Checkliste zum wirksamen Design, der Editor bindet die Bogen-Auswahl
 * live an den Canvas und speichert sie mit dem Entwurf.
 */
class LetterheadPreviewTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{0: Organization, 1: User} */
    private function makeOrgAdmin(): array {
        $org = Organization::factory()->create();
        $this->app->instance('currentOrganization', $org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        return [$org, $admin];
    }

    /** PNG mit gegebenem Seitenverhältnis (A4 hoch = 210:297). */
    private function png(int $width, int $height, string $name = 'bogen.png'): UploadedFile {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 240, 240, 255));
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    private function upload(User $admin, UploadedFile $file, string $name = 'Bogen'): LetterheadAsset {
        $this->actingAs($admin)->post(route('admin.document-design.assets.store'), [
            'name' => $name,
            'page_role' => 'first',
            'file' => $file,
        ]);

        return LetterheadAsset::query()->where('name', $name)->firstOrFail();
    }

    public function test_review_required_asset_is_viewable_and_downloadable_from_index(): void {
        [, $admin] = $this->makeOrgAdmin();
        // Querformat-Bild → Seitenverhältnis passt nicht → keine Rasterseite.
        $asset = $this->upload($admin, $this->png(900, 600));
        $this->assertSame(LetterheadAssetStatus::ReviewRequired, $asset->status);
        $this->assertNull($asset->normalized_path);

        $this->actingAs($admin)->get(route('admin.document-design.index'))
            ->assertOk()
            ->assertSee(route('admin.document-design.assets.show', $asset->sqid))
            ->assertSee(route('admin.document-design.assets.original', $asset->sqid))
            ->assertSee('data-design-checklist', false)
            ->assertSee(__('document_design.checklist.step.letterhead_review'));
    }

    public function test_preview_dialog_shows_original_image_and_review_notes(): void {
        [, $admin] = $this->makeOrgAdmin();
        $asset = $this->upload($admin, $this->png(900, 600));

        $this->actingAs($admin)->get(route('admin.document-design.assets.show', $asset->sqid))
            ->assertOk()
            ->assertSee(route('admin.document-design.assets.original', $asset->sqid))
            ->assertSee(__('document_design.asset.preview_of_original'))
            ->assertSee(__('document_design.asset.review_notes'))
            ->assertSee((string) ($asset->review_notes[0] ?? ''));
    }

    public function test_original_image_is_served_inline_and_pdf_only_as_attachment(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $image = $this->upload($admin, $this->png(900, 600));

        $this->actingAs($admin)->get(route('admin.document-design.assets.original', $image->sqid))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename="bogen.png"');

        // PDF-Original ohne Rasterseite: nie im Browser-Viewer öffnen.
        Storage::disk('local')->put('document-design/orig/brief.pdf', "%PDF-1.4\n%fake\n");
        $pdf = LetterheadAsset::query()->create([
            'organization_id' => $org->id,
            'name' => 'Brief',
            'page_role' => LetterheadPageRole::First,
            'source_type' => 'pdf',
            'disk' => 'local',
            'original_path' => 'document-design/orig/brief.pdf',
            'original_name' => 'Brief Bogen.pdf',
            'mime' => 'application/pdf',
            'size' => 14,
            'original_sha256' => str_repeat('a', 64),
            'status' => LetterheadAssetStatus::ReviewRequired,
            'review_notes' => ['Die PDF-Seite konnte nicht sicher gerastert werden.'],
        ]);

        $this->actingAs($admin)->get(route('admin.document-design.assets.original', $pdf->sqid))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="brief-bogen.pdf"');

        $this->actingAs($admin)->get(route('admin.document-design.assets.show', $pdf->sqid))
            ->assertOk()
            ->assertSee(__('document_design.asset.no_preview'))
            ->assertSee(__('document_design.asset.download_original'));
    }

    public function test_dialog_and_original_are_tenant_isolated(): void {
        [, $admin] = $this->makeOrgAdmin();
        $asset = $this->upload($admin, $this->png(900, 600));

        $foreignOrg = Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $foreignOrg->id]);
        $this->app->instance('currentOrganization', $foreignOrg);

        $this->actingAs($foreignAdmin)->get(route('admin.document-design.assets.show', $asset->sqid))->assertNotFound();
        $this->actingAs($foreignAdmin)->get(route('admin.document-design.assets.original', $asset->sqid))->assertNotFound();
    }

    public function test_checklist_disappears_once_design_is_active_and_assigned(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $asset = $this->upload($admin, $this->png(630, 891));
        $this->assertTrue($asset->isReady());

        $service = app(RenderProfileService::class);
        $profile = $service->createProfile($org, 'CI-Basisdesign', [], true, $admin);
        $draft = $profile->versions()->firstOrFail();
        $service->updateDraft($draft, ['first_asset_id' => $asset->id], $admin);
        $this->assertTrue($service->activate($draft, $admin)->ok());

        $this->actingAs($admin)->get(route('admin.document-design.index'))
            ->assertOk()
            ->assertDontSee('data-design-checklist', false);
    }

    public function test_editor_exposes_live_previews_and_saves_assignment_with_the_draft(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $asset = $this->upload($admin, $this->png(630, 891));
        $profile = app(RenderProfileService::class)->createProfile($org, 'Rechnungen', ['invoice'], false, $admin);

        $previewUrl = route('admin.document-design.assets.preview', $asset->sqid);
        $this->actingAs($admin)->get(route('admin.document-design.editor', $profile->sqid))
            ->assertOk()
            ->assertSee('assetPreviews', false)
            ->assertSee(e((string) json_encode($previewUrl)), false)
            ->assertSee('setTab(', false)
            ->assertSee(__('document_design.editor.tab_appearance'));

        $draft = $profile->versions()->firstOrFail();
        $this->actingAs($admin)->putJson(route('admin.document-design.draft.update', $profile->sqid), [
            'layout' => $draft->layout,
            'block_rules' => $draft->block_rules,
            'table_style' => $draft->table_style,
            'content_texts' => ['header_text' => null, 'footer_text' => null],
            'first_asset' => $asset->sqid,
            'following_asset' => '',
        ])->assertOk()->assertJson(['saved' => true]);

        $this->assertSame($asset->id, DocumentRenderProfileVersion::query()->findOrFail($draft->id)->first_asset_id);
    }

    public function test_feed_and_branding_link_to_document_design(): void {
        [, $admin] = $this->makeOrgAdmin();

        $this->actingAs($admin)->get(route('billing.feed'))
            ->assertOk()
            ->assertSee(route('admin.document-design.index'));
        $this->actingAs($admin)->get(route('admin.branding.edit'))
            ->assertOk()
            ->assertSee(route('admin.document-design.index'))
            ->assertSee(__('document_design.link.open'));
    }
}
