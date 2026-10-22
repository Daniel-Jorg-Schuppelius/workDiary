<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\Import\ImportEntity;
use App\Enums\Learning\LearningQuestionKind;
use App\Models\Learning\{LearningQuestion, LearningQuestionCategory};
use App\Models\Platform\User;
use App\Services\Import\{EntitySpecRegistry, ImportOutcome};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Fragen per Datei in den Fragenkatalog (MVP-1262). */
final class LearningQuestionImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_rows_create_questions_with_options_category_and_skip_duplicates(): void {
        $spec = app(EntitySpecRegistry::class)->for(ImportEntity::LearningQuestions);
        $row = $spec->normalize([
            'kind' => LearningQuestionKind::Single->label(),
            'prompt' => 'Welche Farbe hat ein Feuerlöscher?',
            'options' => "*Rot\nBlau\nGrün {0}",
            'category' => 'Brandschutz',
            'points' => '2',
            'hint' => 'Denken Sie an die Wand im Flur.',
        ]);

        $this->assertSame([], $spec->validateRow($row, $this->organization));
        $this->assertSame(ImportOutcome::Created, $spec->upsert($row, $this->organization)[0]);
        $this->assertSame(ImportOutcome::Skipped, $spec->upsert($row, $this->organization)[0]);

        $question = LearningQuestion::query()->sole();
        $this->assertSame(LearningQuestionKind::Single, $question->kind);
        $this->assertSame(2, (int) $question->points);
        $this->assertSame('Brandschutz', LearningQuestionCategory::query()->findOrFail($question->learning_question_category_id)->name);
        $options = $question->options()->orderBy('position')->get();
        $this->assertSame(['Rot', 'Blau', 'Grün'], $options->pluck('label')->all());
        $this->assertTrue((bool) $options[0]->is_correct);

        $matching = $spec->normalize(['kind' => 'matching', 'prompt' => 'Ordnen Sie zu', 'options' => "CO2 = Elektrobrand\nWasser = Holz"]);
        $this->assertSame(ImportOutcome::Created, $spec->upsert($matching, $this->organization)[0]);
        $this->assertSame(1, LearningQuestionCategory::query()->count());
    }

    public function test_invalid_rows_are_reported(): void {
        $spec = app(EntitySpecRegistry::class)->for(ImportEntity::LearningQuestions);

        $this->assertNotEmpty($spec->validateRow($spec->normalize(['kind' => 'unbekannt', 'prompt' => 'Frage']), $this->organization));
        $this->assertNotEmpty($spec->validateRow($spec->normalize(['kind' => 'single', 'prompt' => 'Ohne Antworten']), $this->organization));
        $this->assertNotEmpty($spec->validateRow($spec->normalize(['kind' => 'hotspot', 'prompt' => 'Wo ist der Notausgang?']), $this->organization));
        $this->assertNotEmpty($spec->validateRow($spec->normalize(['kind' => 'essay', 'prompt' => 'Beschreiben Sie', 'points' => '0']), $this->organization));
        $this->assertSame([], $spec->validateRow($spec->normalize(['kind' => 'essay', 'prompt' => 'Beschreiben Sie']), $this->organization));
    }

    public function test_catalog_links_to_the_import_with_template(): void {
        $author = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($author)->get(route('learning.questions.index'))
            ->assertOk()
            ->assertSee(route('admin.imports.create', ['entity' => ImportEntity::LearningQuestions->value]), false);
        $this->actingAs($author)->get(route('admin.imports.template', ImportEntity::LearningQuestions->value))
            ->assertOk()
            ->assertSee('kind', false);
    }
}
