<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningContentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningBlockKind;
use App\Models\Learning\LearningUnit;
use App\Models\User;
use App\Services\Learning\{LearningContentService, LearningCourseService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Autorenwerkzeug (Feature 149, MVP-736): Inhaltsblöcke sind strukturiert
 * statt freies HTML, Einbettungen brauchen einen freigegebenen Host, und
 * ein freigegebener Kurs bleibt gesperrt.
 */
class LearningContentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function content(): LearningContentService {
        return app(LearningContentService::class);
    }

    private function unit(): LearningUnit {
        $course = app(LearningCourseService::class)->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        app(LearningCourseService::class)->addUnit($course, ['title' => 'Einführung']);

        return $course->refresh()->units()->firstOrFail();
    }

    private function author(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    public function test_block_wird_auf_das_feldbild_seines_typs_reduziert(): void {
        $unit = $this->unit();

        $blocks = $this->content()->appendBlock($unit, LearningBlockKind::Text, [
            'text' => 'Fluchtwege freihalten.',
            'url' => 'https://example.test/ignoriert',
            'require_percent' => 80,
        ]);

        $this->assertCount(1, $blocks);
        $this->assertSame(['type' => 'text', 'text' => 'Fluchtwege freihalten.'], $blocks[0]);
    }

    public function test_checkliste_wird_zeilenweise_zerlegt(): void {
        $unit = $this->unit();

        $blocks = $this->content()->appendBlock($unit, LearningBlockKind::Checklist, [
            'items' => "Feuerlöscher prüfen\n\nFluchtweg freihalten\n",
        ]);

        $this->assertSame(['Feuerlöscher prüfen', 'Fluchtweg freihalten'], $blocks[0]['items']);
    }

    public function test_leerer_block_wird_abgewiesen(): void {
        $unit = $this->unit();

        $this->expectException(ValidationException::class);
        $this->content()->appendBlock($unit, LearningBlockKind::Text, ['text' => '']);
    }

    public function test_einbettung_ohne_freigegebenen_host_wird_abgewiesen(): void {
        $unit = $this->unit();

        $this->expectException(ValidationException::class);
        $this->content()->appendBlock($unit, LearningBlockKind::Embed, ['url' => 'https://video.example.test/abc']);
    }

    public function test_einbettung_mit_freigegebenem_host_wird_uebernommen(): void {
        $this->organization->update(['settings' => ['learning' => ['embed_hosts' => ['example.test']]]]);
        $unit = $this->unit();

        $blocks = $this->content()->appendBlock($unit->refresh(), LearningBlockKind::Embed, [
            'url' => 'https://video.example.test/abc',
            'caption' => 'Unterweisungsvideo',
        ]);

        $this->assertSame('https://video.example.test/abc', $blocks[0]['url']);
    }

    public function test_bloecke_lassen_sich_sortieren_und_entfernen(): void {
        $unit = $this->unit();
        $this->content()->appendBlock($unit, LearningBlockKind::Heading, ['text' => 'Erstes']);
        $this->content()->appendBlock($unit->refresh(), LearningBlockKind::Text, ['text' => 'Zweites']);

        $moved = $this->content()->moveBlock($unit->refresh(), 1, -1);
        $this->assertSame('Zweites', $moved[0]['text']);

        $left = $this->content()->removeBlock($unit->refresh(), 0);
        $this->assertCount(1, $left);
        $this->assertSame('Erstes', $left[0]['text']);
    }

    public function test_editor_ist_nach_der_freigabe_gesperrt(): void {
        $author = $this->author();
        $unit = $this->unit();
        $course = $unit->course;
        app(LearningCourseService::class)->release($course, $author);

        $this->actingAs($author)
            ->get(route('learning.courses.units.edit', [$course, $unit]))
            ->assertForbidden();
    }

    public function test_editor_legt_einen_block_ueber_das_formular_an(): void {
        $author = $this->author();
        $unit = $this->unit();

        $this->actingAs($author)
            ->post(route('learning.courses.units.blocks.store', [$unit->course, $unit]), [
                'type' => LearningBlockKind::Callout->value,
                'text' => 'Im Brandfall zuerst Menschen retten.',
                'tone' => 'warning',
            ])
            ->assertRedirect();

        $blocks = $unit->refresh()->blocks();
        $this->assertSame('callout', $blocks[0]['type']);
        $this->assertSame('warning', $blocks[0]['tone']);
    }

    public function test_freischaltplan_wird_an_der_einheit_gespeichert(): void {
        $author = $this->author();
        $unit = $this->unit();

        $this->actingAs($author)
            ->put(route('learning.courses.units.update', [$unit->course, $unit]), [
                'title' => 'Einführung',
                'release_after_days' => 7,
                'is_mandatory' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(['after_days' => 7], $unit->refresh()->release_rule);
    }

    public function test_einheit_eines_fremden_kurses_ist_im_editor_unsichtbar(): void {
        $author = $this->author();
        $unitA = $this->unit();
        $unitB = $this->unit();

        $this->actingAs($author)
            ->get(route('learning.courses.units.edit', [$unitA->course, $unitB]))
            ->assertNotFound();
    }

    // ── Medien-Blöcke und Darstellung (MVP-736) ─────────────────────────
    //
    // Der Player rendete zuvor NUR Textblöcke: Überschriften, Hinweise,
    // Checklisten, Bilder, Dateien, Videos und Einbettungen waren im Kurs
    // unsichtbar, obwohl der Editor sie anlegte.

    public function test_bild_ohne_alternativtext_wird_abgelehnt(): void {
        Storage::fake('local');
        $unit = $this->unit();

        // Ohne Alternativtext ist das Bild für Menschen, die es nicht sehen
        // können, nicht vorhanden (BFSG/WCAG 1.1.1).
        $this->actingAs($this->author())
            ->post(route('learning.courses.units.blocks.store', [$unit->course->sqid, $unit->sqid]), [
                'type' => LearningBlockKind::Image->value,
                'media' => UploadedFile::fake()->image('plan.png'),
            ])
            ->assertSessionHasErrors('alt');
    }

    public function test_bildblock_bekommt_die_datei_als_anhang_der_einheit(): void {
        Storage::fake('local');
        $unit = $this->unit();

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.blocks.store', [$unit->course->sqid, $unit->sqid]), [
                'type' => LearningBlockKind::Image->value,
                'alt' => 'Fluchtwegplan Halle A',
                'caption' => 'Halle A',
                'media' => UploadedFile::fake()->image('plan.png'),
            ])
            ->assertRedirect();

        $unit->refresh();
        $attachment = $unit->attachments()->firstOrFail();
        $block = $unit->blocks()[0];

        // Ohne attachment_id bliebe der Block im Kurs leer.
        $this->assertSame($attachment->id, $block['attachment_id'] ?? null);
        $this->assertSame('Fluchtwegplan Halle A', $block['alt'] ?? null);
    }

    public function test_player_zeigt_alle_blockarten_nicht_nur_text(): void {
        Storage::fake('local');
        $unit = $this->unit();
        $content = $this->content();

        $content->appendBlock($unit, LearningBlockKind::Heading, ['text' => 'Erster Abschnitt']);
        $content->appendBlock($unit, LearningBlockKind::Callout, ['text' => 'Achtung Stolperkante', 'tone' => 'warning']);
        $content->appendBlock($unit, LearningBlockKind::Checklist, ['items' => "Helm auf\nWeste an"]);

        $course = $unit->course;
        app(LearningCourseService::class)->release($course->refresh(), null);

        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(\App\Services\Learning\LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $this->actingAs($learner)
            ->get(route('learning.my.show', $enrollment->sqid))
            ->assertOk()
            ->assertSee('Erster Abschnitt')
            ->assertSee('Achtung Stolperkante')
            ->assertSee('Helm auf');
    }

    public function test_medien_fremder_einheit_sind_nicht_abrufbar(): void {
        Storage::fake('local');
        $unitA = $this->unit();
        $unitB = $this->unit();

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.blocks.store', [$unitB->course->sqid, $unitB->sqid]), [
                'type' => LearningBlockKind::File->value,
                'caption' => 'Merkblatt',
                'media' => UploadedFile::fake()->create('merkblatt.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $foreign = $unitB->refresh()->attachments()->firstOrFail();

        // Sonst wäre die Route ein Leseschlüssel auf jede Datei der Anwendung.
        $this->actingAs($this->author())
            ->get(route('learning.courses.units.media', [$unitA->course->sqid, $unitA->sqid, $foreign->sqid]))
            ->assertNotFound();
    }

    // ── Kursinhalt offline lesen (MVP-748) ──────────────────────────────

    public function test_offline_bundle_laesst_pruefungen_und_medien_aus(): void {
        Storage::fake('local');
        $unit = $this->unit();
        $course = $unit->course;
        $content = $this->content();

        $content->appendBlock($unit, LearningBlockKind::Text, ['text' => 'Fluchtwege freihalten.']);

        // Bildblock: die Beschriftung darf mit, die Datei nicht.
        $this->actingAs($this->author())
            ->post(route('learning.courses.units.blocks.store', [$course->sqid, $unit->sqid]), [
                'type' => LearningBlockKind::Image->value,
                'alt' => 'Fluchtwegplan',
                'caption' => 'Halle A',
                'media' => UploadedFile::fake()->image('plan.png'),
            ])
            ->assertRedirect();

        app(LearningCourseService::class)->addUnit($course->refresh(), [
            'title' => 'Abschlussprüfung',
            'kind' => \App\Enums\Learning\LearningUnitKind::Quiz->value,
        ]);
        app(LearningCourseService::class)->release($course->refresh(), null);

        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(\App\Services\Learning\LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $response = $this->actingAs($learner)->getJson(route('learning.my.offline', $enrollment->sqid));

        $response->assertOk();
        $data = $response->json();

        // Nur die Textseite — die Prüfung ist online-pflichtig, eine Frage im
        // Gerätespeicher wäre die Lösung gleich mitgeliefert.
        $this->assertCount(1, $data['units']);
        $this->assertSame('Einführung', $data['units'][0]['title']);

        $blocks = $data['units'][0]['blocks'];
        $this->assertSame('Fluchtwege freihalten.', $blocks[0]['text']);

        // Beschriftung ja, Datei nein: sonst läge Bildmaterial im Gerät.
        $this->assertSame('Halle A', $blocks[1]['caption']);
        $this->assertArrayNotHasKey('attachment_id', $blocks[1]);

        $this->extractedCleanup($unit);
    }

    public function test_offline_bundle_fremder_einschreibung_ist_nicht_abrufbar(): void {
        $unit = $this->unit();
        app(LearningCourseService::class)->release($unit->course->refresh(), null);

        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(\App\Services\Learning\LearningEnrollmentService::class)->enroll($unit->course->refresh(), $learner);
        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($other)
            ->getJson(route('learning.my.offline', $enrollment->sqid))
            ->assertNotFound();
    }

    /** Angehängte Dateien aus dem echten Speicher räumen. */
    private function extractedCleanup(\App\Models\Learning\LearningUnit $unit): void {
        foreach ($unit->refresh()->attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
    }
}
