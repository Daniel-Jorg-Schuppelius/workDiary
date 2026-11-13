<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeGuideTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\Knowledge\KnowledgePageKind;
use App\Models\Attachments\Attachment;
use App\Models\Knowledge\{KnowledgeGuideStep, KnowledgePage, KnowledgePageVersion, KnowledgeSpace};
use App\Models\Platform\User;
use App\Services\Knowledge\Pages\KnowledgePageService;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Bildanleitung und Notfallhandbuch (MVP-1668): Schritte mit Bild,
 * Markierungen, Schwärzen verwirft das Original, Abspielen, PDF der Seite
 * und des Notfallhandbuchs.
 */
final class KnowledgeGuideTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private KnowledgeSpace $space;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->space = KnowledgeSpace::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_steps_are_marked_redacted_and_played(): void {
        $guide = $this->guide();
        $this->actingAs($this->admin)->post(route('knowledge.pages.steps.store', $guide), [
            'image' => UploadedFile::fake()->createWithContent('anmeldung.png', $this->whitePng()),
            'body' => 'Benutzernamen eintragen',
        ])->assertRedirect();

        $step = KnowledgeGuideStep::query()->where('knowledge_page_id', $guide->id)->firstOrFail();
        $image = Attachment::query()->findOrFail($step->attachment_id);
        $this->assertSame(KnowledgePage::META_GUIDE_STEP, $image->meta_type);
        $original = Storage::disk('local')->get($image->path);

        $this->actingAs($this->admin)->get(route('knowledge.pages.steps.edit', [$guide, $step]))->assertOk()->assertSee('data-guide-editor', false);

        $this->actingAs($this->admin)->put(route('knowledge.pages.steps.update', [$guide, $step]), [
            'body' => 'Benutzernamen eintragen und bestätigen',
            'markers' => JsonHelper::encode([
                ['kind' => 'number', 'x' => 0.2, 'y' => 0.2, 'label' => '1'],
                ['kind' => 'frame', 'x' => 0.1, 'y' => 0.1, 'w' => 0.3, 'h' => 0.2],
                ['kind' => 'bogus', 'x' => 0.5, 'y' => 0.5],
            ]),
            'redactions' => JsonHelper::encode([['x' => 0.0, 'y' => 0.0, 'w' => 0.5, 'h' => 0.5]]),
        ])->assertSessionHasNoErrors();

        $step->refresh();
        $this->assertCount(2, $step->markers ?? []);
        $this->assertNotNull($step->redacted_at);
        $redacted = Storage::disk('local')->get($image->refresh()->path);
        $this->assertNotSame($original, $redacted, 'Das ungeschwärzte Original muss verworfen sein.');
        $pixels = imagecreatefromstring((string) $redacted);
        $this->assertNotFalse($pixels);
        $this->assertSame(0, imagecolorat($pixels, 1, 1) & 0xFFFFFF, 'Der geschwärzte Bereich ist schwarz.');
        $this->assertSame(0xFFFFFF, imagecolorat($pixels, 15, 15) & 0xFFFFFF, 'Außerhalb bleibt das Bild unverändert.');

        // Die offene Fassung trägt die Schritte; abspielen zeigt Schritt 1.
        $snapshot = KnowledgePageVersion::query()->where('knowledge_page_id', $guide->id)->where('version', $guide->refresh()->version)->firstOrFail();
        $this->assertSame('Benutzernamen eintragen und bestätigen', $snapshot->steps[0]['body'] ?? null);
        $this->actingAs($this->admin)->get(route('knowledge.pages.play', $guide))->assertOk()->assertSee('Benutzernamen eintragen und bestätigen');
    }

    public function test_page_and_emergency_handbook_pdf(): void {
        $service = app(KnowledgePageService::class);
        $page = $service->create($this->space, null, null, $this->admin, ['title' => 'Serverausfall', 'body' => "## Sofort\nBereitschaft anrufen."]);
        $page->forceFill(['is_emergency' => true])->save();
        $service->approve($page, $this->admin);

        $this->actingAs($this->admin)->get(route('knowledge.pages.pdf', $page))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $response = $this->actingAs($this->admin)->get(route('knowledge.pages.emergency-pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    private function guide(): KnowledgePage {
        return app(KnowledgePageService::class)->create($this->space, null, null, $this->admin, [
            'title' => 'Anmelden Schritt für Schritt',
            'kind' => KnowledgePageKind::Guide->value,
            'body' => 'So melden Sie sich an.',
        ]);
    }

    private function whitePng(): string {
        $image = imagecreatetruecolor(20, 20);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
