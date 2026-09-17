<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningBlockTypesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningBlockKind, LearningTranslationStatus};
use App\Jobs\TranscodeVideoJob;
use App\Models\Learning\{LearningContentTranslation, LearningUnit};
use App\Models\{Organization, ProcedureStepDef, ProcedureTemplate, ProcedureTemplateVersion, User};
use App\Services\Learning\{LearningContentService, LearningCourseService, LearningEnrollmentService, LearningTranslationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Queue, Storage};
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Die acht Blockarten aus MVP-806 (Feature 149): Galerie, Audio, Code,
 * Akkordeon, Tabelle, Prozedur, Verständnisfrage, Trenner — je mit dem, was
 * sie für Menschen ohne Sicht, Gehör oder Maus brauchen.
 */
class LearningBlockTypesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_galerie_speichert_jedes_bild_mit_eigenem_alternativtext(): void {
        $unit = $this->unit();

        $this->actingAs($this->author())
            ->post($this->storeRoute($unit), [
                'type' => LearningBlockKind::Gallery->value,
                'caption' => 'Halle A',
                'gallery' => [UploadedFile::fake()->image('nord.png'), UploadedFile::fake()->image('sued.png')],
                'alts' => "Notausgang Nord mit Schild\nNotausgang Süd mit Feuerlöscher",
            ])
            ->assertSessionHasNoErrors();

        $block = $unit->refresh()->blocks()[0];
        $this->assertSame('gallery', $block['type']);
        $this->assertSame(['Notausgang Nord mit Schild', 'Notausgang Süd mit Feuerlöscher'], array_column($block['images'], 'alt'));
        $this->assertEqualsCanonicalizing($unit->attachments()->pluck('id')->all(), array_column($block['images'], 'attachment_id'));
    }

    public function test_galerie_ohne_alternativtext_je_bild_legt_keine_anhaenge_an(): void {
        $unit = $this->unit();

        $this->actingAs($this->author())
            ->post($this->storeRoute($unit), [
                'type' => LearningBlockKind::Gallery->value,
                'gallery' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')],
                'alts' => 'Nur ein Text für zwei Bilder',
            ])
            ->assertSessionHasErrors('alts');

        // Vor dem Speichern geprüft: keine verwaisten Dateien.
        $this->assertSame(0, $unit->attachments()->count());
        $this->assertSame([], $unit->refresh()->blocks());
    }

    public function test_audio_braucht_transkript_und_eine_audiodatei(): void {
        $unit = $this->unit();
        $author = $this->author();

        $this->actingAs($author)
            ->post($this->storeRoute($unit), [
                'type' => LearningBlockKind::Audio->value,
                'media' => UploadedFile::fake()->create('einweisung.mp3', 40, 'audio/mpeg'),
            ])
            ->assertSessionHasErrors('text');

        $this->actingAs($author)
            ->post($this->storeRoute($unit), [
                'type' => LearningBlockKind::Audio->value,
                'text' => 'Transkript',
                'media' => UploadedFile::fake()->create('merkblatt.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHasErrors('media');

        $this->actingAs($author)
            ->post($this->storeRoute($unit), [
                'type' => LearningBlockKind::Audio->value,
                'text' => 'Guten Morgen, heute geht es um Fluchtwege.',
                'caption' => 'Einweisung',
                'media' => UploadedFile::fake()->create('einweisung.mp3', 40, 'audio/mpeg'),
            ])
            ->assertSessionHasNoErrors();

        $blocks = $unit->refresh()->blocks();
        $this->assertCount(1, $blocks);
        $this->assertSame('Guten Morgen, heute geht es um Fluchtwege.', $blocks[0]['text']);
        $this->assertArrayHasKey('attachment_id', $blocks[0]);
    }

    public function test_videoblock_nimmt_videos_an_und_reiht_die_umrechnung_ein(): void {
        // Vorher lehnte die Uploadregel jedes Video ab (nur Bild-/Dokumentendungen):
        // Videoupload und Umrechnung waren über die Oberfläche nie erreichbar.
        Queue::fake();
        $unit = $this->unit();

        $this->actingAs($this->author())
            ->post($this->storeRoute($unit), [
                'type' => LearningBlockKind::Video->value,
                'caption' => 'Rundgang',
                'media' => UploadedFile::fake()->create('rundgang.mp4', 200, 'video/mp4'),
            ])
            ->assertSessionHasNoErrors();

        $attachment = $unit->attachments()->firstOrFail();
        $this->assertSame($attachment->id, $unit->refresh()->blocks()[0]['attachment_id'] ?? null);
        Queue::assertPushed(TranscodeVideoJob::class);
    }

    public function test_bildblock_lehnt_eine_fremde_dateiart_ab(): void {
        $unit = $this->unit();

        $this->actingAs($this->author())
            ->post($this->storeRoute($unit), [
                'type' => LearningBlockKind::Image->value,
                'alt' => 'Plan',
                'media' => UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHasErrors('media');

        $this->assertSame(0, $unit->attachments()->count());
    }

    public function test_akkordeon_tabelle_und_frage_werden_strukturiert_gespeichert(): void {
        $unit = $this->unit();
        $content = app(LearningContentService::class);

        $content->appendBlock($unit, LearningBlockKind::Accordion, [
            'sections' => "Wann?\nVor jeder Schicht.\n\nWer?\nJede Person\nim Bereich.",
        ]);
        $content->appendBlock($unit, LearningBlockKind::Table, [
            'rows' => "| Mittel | Ort |\n|---|---|\n| Feuerlöscher | Halle A |\n| Verbandkasten\n",
            'caption' => 'Notfallmittel',
        ]);
        $content->appendBlock($unit, LearningBlockKind::Table, [
            'rows' => "Stoff\tKlasse\nAceton\t3",
        ]);
        $blocks = $content->appendBlock($unit, LearningBlockKind::Question, [
            'text' => 'Wohin im Brandfall?',
            'items' => "Zum Parkplatz\n* Zum Sammelplatz\n",
            'explanation' => 'Der Sammelplatz steht im Plan.',
        ]);

        $this->assertSame([
            ['title' => 'Wann?', 'body' => 'Vor jeder Schicht.'],
            ['title' => 'Wer?', 'body' => "Jede Person\nim Bereich."],
        ], $blocks[0]['sections']);
        // Markdown-Trennzeile entfällt, die kurze Zeile wird aufgefüllt.
        $this->assertSame([['Mittel', 'Ort'], ['Feuerlöscher', 'Halle A'], ['Verbandkasten', '']], $blocks[1]['rows']);
        $this->assertSame([['Stoff', 'Klasse'], ['Aceton', '3']], $blocks[2]['rows']);
        $this->assertSame([
            ['text' => 'Zum Parkplatz', 'correct' => false],
            ['text' => 'Zum Sammelplatz', 'correct' => true],
        ], $blocks[3]['options']);
        $this->assertArrayNotHasKey('items', $blocks[3]);
    }

    public function test_unvollstaendige_strukturen_werden_abgewiesen(): void {
        $unit = $this->unit();
        $content = app(LearningContentService::class);

        $cases = [
            'Tabelle ohne Datenzeile' => [LearningBlockKind::Table, ['rows' => 'Nur | Kopf']],
            'Tabelle mit leerem Kopf' => [LearningBlockKind::Table, ['rows' => "Mittel |\nLöscher | Halle"]],
            'Akkordeon ohne Inhalt' => [LearningBlockKind::Accordion, ['sections' => "Nur Überschrift\n\nZweite\nMit Inhalt"]],
            'Frage ohne richtige Antwort' => [LearningBlockKind::Question, ['text' => 'Frage?', 'items' => "A\nB"]],
            'Frage ohne Auflösung' => [LearningBlockKind::Question, ['text' => 'Frage?']],
            'Frage ohne Text' => [LearningBlockKind::Question, ['items' => "* A\nB"]],
            'Audio ohne Datei' => [LearningBlockKind::Audio, ['text' => 'Transkript']],
            'Galerie mit einem Bild' => [LearningBlockKind::Gallery, ['images' => [['attachment_id' => 1, 'alt' => 'Bild']]]],
        ];

        foreach ($cases as $label => [$kind, $payload]) {
            try {
                $content->appendBlock($unit, $kind, $payload);
                $this->fail("{$label}: nicht abgewiesen.");
            } catch (ValidationException) {
                $this->assertSame([], $unit->refresh()->blocks(), $label);
            }
        }
    }

    public function test_prozedur_nur_aktiv_und_aus_der_eigenen_organisation(): void {
        $unit = $this->unit();
        $author = $this->author();
        $foreign = ProcedureTemplate::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $inactive = ProcedureTemplate::factory()->inactive()->create(['organization_id' => $this->organization->id]);
        $own = ProcedureTemplate::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Freischalten']);

        foreach ([$foreign, $inactive] as $template) {
            $this->actingAs($author)
                ->post($this->storeRoute($unit), ['type' => LearningBlockKind::Procedure->value, 'procedure_template_id' => $template->sqid])
                ->assertSessionHasErrors('procedure_template_id');
        }

        $this->actingAs($author)
            ->post($this->storeRoute($unit), ['type' => LearningBlockKind::Procedure->value, 'procedure_template_id' => $own->sqid])
            ->assertSessionHasNoErrors();

        $this->assertSame($own->id, $unit->refresh()->blocks()[0]['procedure_template_id']);
    }

    public function test_trenner_braucht_keinen_inhalt(): void {
        $unit = $this->unit();

        $blocks = app(LearningContentService::class)->appendBlock($unit, LearningBlockKind::Divider, ['text' => 'wird verworfen']);

        $this->assertSame([['type' => 'divider']], $blocks);
    }

    public function test_player_zeigt_die_neuen_blockarten_zugaenglich(): void {
        $unit = $this->unit();
        $content = app(LearningContentService::class);
        $template = ProcedureTemplate::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Anlage freischalten']);
        $version = ProcedureTemplateVersion::factory()->published()->create(['procedure_template_id' => $template->id]);
        ProcedureStepDef::factory()->create(['procedure_template_version_id' => $version->id, 'label' => 'Spannungsfreiheit feststellen', 'sort_order' => 10]);

        $content->appendBlock($unit, LearningBlockKind::Code, ['text' => 'php artisan down', 'language' => 'Bash']);
        $content->appendBlock($unit, LearningBlockKind::Accordion, ['sections' => "Wann?\nVor jeder Schicht."]);
        $content->appendBlock($unit, LearningBlockKind::Table, ['rows' => "Mittel | Ort\nFeuerlöscher | Halle A", 'caption' => 'Notfallmittel']);
        $content->appendBlock($unit, LearningBlockKind::Question, ['text' => 'Wohin im Brandfall?', 'items' => "Parkplatz\n* Sammelplatz"]);
        $content->appendBlock($unit, LearningBlockKind::Divider, []);
        $content->appendBlock($unit, LearningBlockKind::Procedure, ['procedure_template_id' => $template->id]);

        $learner = $this->learner();
        $response = $this->actingAs($learner)
            ->get(route('learning.my.show', $this->enrollmentFor($unit, $learner)))
            ->assertOk();

        $response->assertSee('<pre', false)
            ->assertSee('tabindex="0"', false)
            ->assertSee('php artisan down')
            ->assertSee('<summary class="collapse-title text-sm font-medium">Wann?</summary>', false)
            ->assertSee('Notfallmittel</caption>', false)
            ->assertSee('<th scope="col">Mittel</th>', false)
            ->assertSee('Wohin im Brandfall?')
            ->assertSee(__('learning.action.show_solution'))
            ->assertSee('<hr', false)
            ->assertSee('Anlage freischalten')
            ->assertSee('Spannungsfreiheit feststellen');
    }

    public function test_freigegebene_uebersetzung_ersetzt_die_texte_strukturierter_bloecke(): void {
        $unit = $this->unit();
        $content = app(LearningContentService::class);
        $content->appendBlock($unit, LearningBlockKind::Accordion, ['sections' => "Wann?\nVor jeder Schicht."]);
        $content->appendBlock($unit, LearningBlockKind::Question, ['text' => 'Wohin?', 'items' => "Parkplatz\n* Sammelplatz"]);
        $unit->refresh();

        LearningContentTranslation::query()->create([
            'organization_id' => $this->organization->id,
            'translatable_type' => $unit->getMorphClass(),
            'translatable_id' => $unit->id,
            'locale' => 'en',
            'payload' => (string) json_encode(['blocks' => [
                ['index' => 0, 'type' => 'accordion', 'sections' => [['title' => 'When?', 'body' => 'Before every shift.']]],
                ['index' => 1, 'type' => 'question', 'text' => 'Where to?', 'options' => [['text' => 'Car park', 'correct' => false], ['text' => 'Assembly point', 'correct' => true]]],
            ]]),
            'source_hash' => app(LearningTranslationService::class)->sourceHash($unit),
            'status' => LearningTranslationStatus::Approved,
        ]);

        $learner = $this->learner(['preferences' => ['locale' => 'en']]);

        $this->actingAs($learner)
            ->get(route('learning.my.show', $this->enrollmentFor($unit, $learner)))
            ->assertOk()
            ->assertSee('When?')
            ->assertSee('Assembly point')
            ->assertDontSee('Sammelplatz');
    }

    public function test_offline_paket_gibt_die_galerie_nur_mit_alternativtexten_aus(): void {
        $unit = $this->unit();
        app(LearningContentService::class)->appendBlock($unit, LearningBlockKind::Gallery, [
            'images' => [['attachment_id' => 11, 'alt' => 'Nord'], ['attachment_id' => 12, 'alt' => 'Süd']],
        ]);
        $learner = $this->learner();

        $data = $this->actingAs($learner)
            ->getJson(route('learning.my.offline', $this->enrollmentFor($unit, $learner)))
            ->assertOk()
            ->json();

        $this->assertSame([['alt' => 'Nord'], ['alt' => 'Süd']], $data['units'][0]['blocks'][0]['images']);
    }

    private function unit(): LearningUnit {
        $course = app(LearningCourseService::class)->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        app(LearningCourseService::class)->addUnit($course, ['title' => 'Einführung']);

        return $course->refresh()->units()->firstOrFail();
    }

    private function author(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function learner(array $attributes = []): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id] + $attributes);
    }

    private function enrollmentFor(LearningUnit $unit, User $learner): string {
        $course = $unit->course;
        app(LearningCourseService::class)->release($course->refresh(), null);

        return app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner)->sqid;
    }

    private function storeRoute(LearningUnit $unit): string {
        return route('learning.courses.units.blocks.store', [$unit->course->sqid, $unit->sqid]);
    }
}
